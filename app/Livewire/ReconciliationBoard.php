<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\TempLocalInventory;
use App\Models\AliasDictionary;
use App\Contracts\ProductMatcherInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReconciliationBoard extends Component
{
    use WithPagination;

    // Acción: El usuario confirma que el producto local y el del proveedor son el mismo
    public function approveMatch(int $localId, string $localCode, string $supplierCode, int $supplierQuantity)
    {
        DB::transaction(function () use ($localId, $localCode, $supplierCode, $supplierQuantity) {
            // 1. Actualizamos el inventario local
            TempLocalInventory::where('id', $localId)->update([
                'is_resolved' => true,
                'resolved_stock' => $supplierQuantity
            ]);

            // 2. Guardamos en el diccionario de alias permanentemente (KISS)
            AliasDictionary::updateOrCreate(
                ['local_code' => $localCode, 'supplier_code' => $supplierCode]
            );
        });

        $this->removeFromScoredCache($localId);
    }

    // Acción: El producto definitivamente no existe en el proveedor
    public function discard(int $localId)
    {
        TempLocalInventory::where('id', $localId)->update([
            'is_resolved' => true,
            'resolved_stock' => 0 // Asumimos agotado si no está en la lista del proveedor
        ]);

        $this->removeFromScoredCache($localId);
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
}