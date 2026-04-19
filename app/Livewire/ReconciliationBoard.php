<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\TempLocalInventory;
use App\Models\TempSupplierInventory;
use App\Models\AliasDictionary;
use App\Contracts\ProductMatcherInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReconciliationBoard extends Component
{
    use WithPagination;

    public array $manualQueries = [];
    public array $manualCandidates = [];

    public function updatedManualQueries(mixed $value, mixed $key): void
    {
        if (!is_numeric($key)) {
            return;
        }

        $localId = (int) $key;
        $query = trim((string) $value);

        if ($query === '') {
            $this->manualCandidates[$localId] = [];
            $this->resetErrorBag('manualQueries.' . $localId);
            return;
        }

        $this->searchSupplierCandidates($localId, true);
    }

    // Acción: El usuario confirma manualmente o automáticamente la vinculación
    public function approveMatch(int $localId, int $supplierId): void
    {
        $this->resetErrorBag('manualQueries.' . $localId);

        $localProduct = TempLocalInventory::query()->find($localId);
        $supplierProduct = TempSupplierInventory::query()->find($supplierId);

        if (!$localProduct || !$supplierProduct) {
            $this->addError('manualQueries.' . $localId, 'No se pudo completar la vinculación. Verifica los datos e intenta nuevamente.');
            return;
        }

        $isAlreadyLinkedToAnotherLocal = AliasDictionary::query()
            ->where('supplier_code', $supplierProduct->code)
            ->where('local_code', '!=', $localProduct->code)
            ->exists();

        if ($isAlreadyLinkedToAnotherLocal) {
            $this->addError('manualQueries.' . $localId, 'Ese producto proveedor ya está vinculado a otro producto local.');
            return;
        }

        DB::transaction(function () use ($localProduct, $supplierProduct) {
            // 1. Actualizamos el inventario local
            TempLocalInventory::where('id', $localProduct->id)->update([
                'is_resolved' => true,
                'resolved_stock' => (int) $supplierProduct->quantity,
            ]);

            // 2. Guardamos en el diccionario de alias permanentemente (KISS)
            AliasDictionary::updateOrCreate(
                ['local_code' => $localProduct->code, 'supplier_code' => $supplierProduct->code]
            );
        });

        unset($this->manualQueries[$localId], $this->manualCandidates[$localId]);
        $this->removeFromScoredCache($localId);
    }

    // Acción: El producto definitivamente no existe en el proveedor
    public function discard(int $localId)
    {
        TempLocalInventory::where('id', $localId)->update([
            'is_resolved' => true,
            'resolved_stock' => 0 // Asumimos agotado si no está en la lista del proveedor
        ]);

        unset($this->manualQueries[$localId], $this->manualCandidates[$localId]);
        $this->removeFromScoredCache($localId);
    }

    public function searchSupplierCandidates(int $localId, bool $silent = false): void
    {
        $query = trim((string) ($this->manualQueries[$localId] ?? ''));

        $this->resetErrorBag('manualQueries.' . $localId);
        $this->manualCandidates[$localId] = [];

        if (mb_strlen($query) < 2) {
            if (!$silent && $query !== '') {
                $this->addError('manualQueries.' . $localId, 'Escribe al menos 2 caracteres para buscar.');
            }

            return;
        }

        $localProduct = TempLocalInventory::query()->find($localId);
        if (!$localProduct) {
            $this->addError('manualQueries.' . $localId, 'No se encontró el producto local a vincular.');
            return;
        }

        $tokens = $this->searchTokens($query);
        if (empty($tokens)) {
            if (!$silent) {
                $this->addError('manualQueries.' . $localId, 'Ingresa una búsqueda válida para continuar.');
            }

            return;
        }

        $linkedSupplierCodes = AliasDictionary::query()
            ->where('local_code', '!=', $localProduct->code)
            ->pluck('supplier_code')
            ->all();

        $candidates = TempSupplierInventory::query()
            ->when(!empty($linkedSupplierCodes), fn ($queryBuilder) => $queryBuilder->whereNotIn('code', $linkedSupplierCodes))
            ->where(function ($queryBuilder) use ($tokens) {
                foreach ($tokens as $token) {
                    $likeToken = '%' . $token . '%';

                    $queryBuilder
                        ->orWhere('code', 'like', $likeToken)
                        ->orWhere('description', 'like', $likeToken)
                        ->orWhere('brand', 'like', $likeToken);
                }
            })
            ->limit(25)
            ->get();

        $this->manualCandidates[$localId] = $candidates
            ->map(function (TempSupplierInventory $supplierProduct) use ($localProduct, $query) {
                return [
                    'id' => (int) $supplierProduct->id,
                    'code' => $supplierProduct->code,
                    'description' => $supplierProduct->description,
                    'brand' => $supplierProduct->brand,
                    'quantity' => (int) $supplierProduct->quantity,
                    'confidence' => $this->calculateManualConfidence($query, $localProduct, $supplierProduct),
                ];
            })
            ->sortByDesc('confidence')
            ->take(8)
            ->values()
            ->all();

        if (!$silent && empty($this->manualCandidates[$localId])) {
            $this->addError('manualQueries.' . $localId, 'No se encontraron productos proveedor disponibles para esa búsqueda.');
        }
    }

    public function render(ProductMatcherInterface $matcher)
    {
        $perPage = 10;
        $page = $this->getPage();

        $scoredProducts = $this->getScoredProducts($matcher);

        $pageItems = collect($scoredProducts)->forPage($page, $perPage)->values();
        $pageIds = $pageItems->pluck('local_id')->all();

        $pageProductsMap = TempLocalInventory::whereIn('id', $pageIds)->get()->keyBy('id');
        $orderedPageProducts = collect($pageIds)
            ->map(fn (int $id) => $pageProductsMap->get($id))
            ->filter()
            ->values();

        $products = new LengthAwarePaginator(
            $orderedPageProducts,
            count($scoredProducts),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        $suggestions = $pageItems
            ->mapWithKeys(fn (array $item) => [$item['local_id'] => $item['suggestion']])
            ->all();

        return view('livewire.reconciliation-board', [
            'products' => $products,
            'suggestions' => $suggestions,
        ]);
    }

    protected function getScoredProducts(ProductMatcherInterface $matcher): array
    {
        $version = (int) Cache::get('reconciliation.sort.version', 1);

        // Clave estable por versión del dataset (evita recomputar todo por cada clic)
        $cacheKey = sprintf('reconciliation.scored.v%s', $version);

        Cache::forever('reconciliation.scored.latest_key', $cacheKey);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($matcher) {
            return TempLocalInventory::where('is_resolved', false)
                ->get()
                ->map(function (TempLocalInventory $product) use ($matcher) {
                    $suggestion = $matcher->findBestMatch($product);
                    $confidence = (int) ($suggestion['confidence'] ?? 0);

                    return [
                        'local_id' => (int) $product->id,
                        'confidence' => $confidence,
                        'suggestion' => $suggestion,
                    ];
                })
                ->sortByDesc('confidence')
                ->values()
                ->all();
        });
    }

    protected function removeFromScoredCache(int $localId): void
    {
        $latestKey = Cache::get('reconciliation.scored.latest_key');

        if (!is_string($latestKey) || $latestKey === '') {
            return;
        }

        $cached = Cache::get($latestKey);
        if (!is_array($cached)) {
            return;
        }

        $filtered = array_values(array_filter($cached, function (array $item) use ($localId): bool {
            return (int) ($item['local_id'] ?? 0) !== $localId;
        }));

        Cache::put($latestKey, $filtered, now()->addMinutes(5));
    }

    protected function touchReconciliationCacheVersion(): void
    {
        if (!Cache::has('reconciliation.sort.version')) {
            Cache::forever('reconciliation.sort.version', 1);
        }

        Cache::increment('reconciliation.sort.version');
    }

    protected function calculateManualConfidence(
        string $query,
        TempLocalInventory $localProduct,
        TempSupplierInventory $supplierProduct
    ): int {
        $normalizedQuery = $this->normalizeForSearch($query);
        $normalizedSupplierText = $this->normalizeForSearch(implode(' ', array_filter([
            $supplierProduct->code,
            $supplierProduct->brand,
            $supplierProduct->description,
        ])));

        $queryHitScore = $normalizedQuery !== '' && str_contains($normalizedSupplierText, $normalizedQuery) ? 100 : 0;
        $codeScore = $this->stringSimilarity($localProduct->code, $supplierProduct->code);
        $descriptionScore = $this->tokenOverlapScore($localProduct->description, $supplierProduct->description);
        $brandScore = $this->stringSimilarity($localProduct->brand, $supplierProduct->brand);

        $weighted = ($queryHitScore * 0.45)
            + ($codeScore * 0.30)
            + ($descriptionScore * 0.20)
            + ($brandScore * 0.05);

        return (int) round($weighted);
    }

    protected function normalizeForSearch(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = mb_strtoupper($value, 'UTF-8');
        $value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? '';

        return trim($value);
    }

    protected function searchTokens(string $value): array
    {
        $normalized = $this->normalizeForSearch($value);

        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/\s+/', $normalized) ?: [];

        return array_values(array_unique(array_filter($parts, fn (string $token): bool => mb_strlen($token) >= 2)));
    }

    protected function stringSimilarity(?string $left, ?string $right): float
    {
        $leftNormalized = $this->normalizeForSearch($left);
        $rightNormalized = $this->normalizeForSearch($right);

        if ($leftNormalized === '' || $rightNormalized === '') {
            return 0.0;
        }

        similar_text($leftNormalized, $rightNormalized, $percent);

        return $percent;
    }

    protected function tokenOverlapScore(?string $left, ?string $right): float
    {
        $leftTokens = $this->searchTokens((string) $left);
        $rightTokens = $this->searchTokens((string) $right);

        if (empty($leftTokens) || empty($rightTokens)) {
            return 0.0;
        }

        $intersection = array_intersect($leftTokens, $rightTokens);
        $union = array_unique(array_merge($leftTokens, $rightTokens));

        if (count($union) === 0) {
            return 0.0;
        }

        return (count($intersection) / count($union)) * 100;
    }
}