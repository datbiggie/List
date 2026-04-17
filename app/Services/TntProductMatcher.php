<?php

namespace App\Services;

use App\Contracts\ProductMatcherInterface;
use App\Models\AliasDictionary;
use App\Models\TempLocalInventory;
use App\Models\TempSupplierInventory;
use Illuminate\Support\Collection;
use TeamTNT\TNTSearch\TNTSearch;

class TntProductMatcher implements ProductMatcherInterface
{
    protected const MIN_CONFIDENCE = 35;

    protected TNTSearch $tnt;
    protected bool $indexSelected = false;
    protected ?array $aliasMap = null;
    protected ?Collection $supplierByCode = null;
    protected ?Collection $supplierById = null;

    public function __construct()
    {
        $this->tnt = new TNTSearch();
        
        $this->tnt->loadConfig([
            'driver'    => 'sqlite',
            'database'  => database_path('database.sqlite'),
            'storage'   => storage_path('app/')
        ]);
    }

    public function buildIndex(): void
    {
        $indexer = $this->tnt->createIndex('supplier.index');
        
        $query = 'SELECT id, code || " " || IFNULL(brand, "") || " " || IFNULL(description, "") AS search_data FROM temp_supplier_inventories;';
        
        $indexer->query($query);
        
        // 1. Abrimos el buffer para atrapar cualquier echo/print de TNTSearch
        ob_start();
        
        // 2. Ejecutamos el indexador (que intentará imprimir "Processed 1000 rows...")
        $indexer->run();
        
        // 3. Cerramos el buffer y destruimos la salida capturada de forma silenciosa
        ob_end_clean();
    }

    public function findBestMatch(TempLocalInventory $localProduct): ?array
    {
        // 1) Atajo por conocimiento histórico (si existe, es 100% confiable)
        $supplierCode = $this->getAliasMap()[$localProduct->code] ?? null;
        if ($supplierCode) {
            $aliasedSupplier = $this->getSupplierByCode()->get($supplierCode);
            if ($aliasedSupplier) {
                return $this->formatMatch($aliasedSupplier, 100);
            }
        }

        if (!$this->indexSelected) {
            $this->tnt->selectIndex('supplier.index');
            $this->tnt->fuzziness = true;
            $this->tnt->fuzzy_distance = 1;
            $this->indexSelected = true;
        }

        $searchString = trim(implode(' ', array_filter([
            $localProduct->code,
            $localProduct->brand,
            $localProduct->description,
        ])));

        if ($searchString === '') {
            return null;
        }

        // 2) Traemos varios candidatos y luego reordenamos con score propio
        $result = $this->tnt->search($searchString, 15);

        if (empty($result['ids'])) {
            return null;
        }

        $supplierProducts = $this->getSupplierById();

        $bestSupplier = null;
        $bestConfidence = 0;

        foreach ($result['ids'] as $supplierId) {
            $candidate = $supplierProducts->get($supplierId);
            if (!$candidate) {
                continue;
            }

            $confidence = $this->calculateConfidence($localProduct, $candidate);

            if ($confidence > $bestConfidence) {
                $bestConfidence = $confidence;
                $bestSupplier = $candidate;
            }
        }

        // 3) Evitar sugerencias basura con similitud muy baja
        if (!$bestSupplier || $bestConfidence < self::MIN_CONFIDENCE) {
            return null;
        }

        return $this->formatMatch($bestSupplier, $bestConfidence);
    }

    protected function formatMatch(TempSupplierInventory $supplierProduct, int $confidence): array
    {
        return [
            'id'          => $supplierProduct->id,
            'code'        => $supplierProduct->code,
            'description' => $supplierProduct->description,
            'quantity'    => $supplierProduct->quantity,
            'confidence'  => $confidence,
        ];
    }

    protected function calculateConfidence(TempLocalInventory $local, TempSupplierInventory $supplier): int
    {
        $codeScore = $this->stringSimilarity($local->code, $supplier->code);
        $descriptionScore = $this->tokenOverlapScore($local->description, $supplier->description);
        $brandScore = $this->stringSimilarity($local->brand, $supplier->brand);
        $numericScore = $this->numericOverlapScore(
            ($local->code ?? '') . ' ' . ($local->description ?? ''),
            ($supplier->code ?? '') . ' ' . ($supplier->description ?? '')
        );

        // Ponderación priorizando código, luego descripción y señales secundarias
        $weighted = ($codeScore * 0.60)
            + ($descriptionScore * 0.25)
            + ($brandScore * 0.10)
            + ($numericScore * 0.05);

        return (int) round($weighted);
    }

    protected function stringSimilarity(?string $a, ?string $b): float
    {
        $left = $this->normalize($a);
        $right = $this->normalize($b);

        if ($left === '' || $right === '') {
            return 0.0;
        }

        similar_text($left, $right, $percent);

        return $percent;
    }

    protected function tokenOverlapScore(?string $a, ?string $b): float
    {
        $leftTokens = $this->tokens($a);
        $rightTokens = $this->tokens($b);

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

    protected function numericOverlapScore(string $a, string $b): float
    {
        preg_match_all('/\d+/', $a, $leftMatches);
        preg_match_all('/\d+/', $b, $rightMatches);

        $left = array_values(array_unique($leftMatches[0] ?? []));
        $right = array_values(array_unique($rightMatches[0] ?? []));

        if (empty($left) || empty($right)) {
            return 0.0;
        }

        $intersection = array_intersect($left, $right);
        $union = array_unique(array_merge($left, $right));

        if (count($union) === 0) {
            return 0.0;
        }

        return (count($intersection) / count($union)) * 100;
    }

    protected function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = mb_strtoupper($value, 'UTF-8');
        $value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? '';

        return trim($value);
    }

    protected function tokens(?string $value): array
    {
        $normalized = $this->normalize($value);

        if ($normalized === '') {
            return [];
        }

        $stopwords = [
            'DE', 'DEL', 'LA', 'EL', 'Y', 'CON', 'SIN', 'PARA',
            'SIST', 'SISTEMA', 'CALIDAD', 'UNIDAD',
        ];

        $parts = preg_split('/\s+/', $normalized) ?: [];

        $filtered = array_filter($parts, function (string $token) use ($stopwords): bool {
            return mb_strlen($token) >= 2 && !in_array($token, $stopwords, true);
        });

        return array_values(array_unique($filtered));
    }

    protected function getAliasMap(): array
    {
        if ($this->aliasMap === null) {
            $this->aliasMap = AliasDictionary::query()
                ->pluck('supplier_code', 'local_code')
                ->toArray();
        }

        return $this->aliasMap;
    }

    protected function getSupplierByCode(): Collection
    {
        if ($this->supplierByCode === null) {
            $this->supplierByCode = TempSupplierInventory::query()->get()->keyBy('code');
        }

        return $this->supplierByCode;
    }

    protected function getSupplierById(): Collection
    {
        if ($this->supplierById === null) {
            $this->supplierById = TempSupplierInventory::query()->get()->keyBy('id');
        }

        return $this->supplierById;
    }
}