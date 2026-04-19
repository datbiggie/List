<?php

namespace App\Services;

use App\Contracts\InventoryParserInterface;
use App\Imports\LocalInventoryImport;
use App\Imports\SupplierInventoryImport;
use App\Models\AliasDictionary;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\TempLocalInventory;
use App\Models\TempSupplierInventory;
use App\Events\SupplierInventoryImported;
use Illuminate\Support\Facades\DB; // <-- Importante añadir el Facade DB
use Illuminate\Support\Facades\Cache;

class ExcelInventoryParser implements InventoryParserInterface
{
    public function parseLocal(string $filePath): void
    {
        TempLocalInventory::truncate();
        Excel::import(new LocalInventoryImport, $filePath);

        $this->bumpReconciliationCacheVersion();
    }

    public function parseSupplier(string $filePath): void
    {
        TempSupplierInventory::truncate();
        Excel::import(new SupplierInventoryImport, $filePath);

        $this->bumpReconciliationCacheVersion();

        // NUEVO: Fase de automatización silenciosa
        $this->autoResolveExactMatches();
        $this->autoResolveBrandAwareExactConflicts();
        $this->autoResolveNearCodeMatches();

        // Disparamos el evento para que TNTSearch indexe SOLO lo que quedó pendiente
        SupplierInventoryImported::dispatch();
    }

    /**
     * Resuelve masivamente y en milisegundos las coincidencias exactas usando SQLite.
     */
    protected function autoResolveExactMatches(): void
    {
        // 1. Auto-aprobar coincidencias exactas por Código (Ej: 8260 == 8260)
        DB::statement('
            UPDATE temp_local_inventories
            SET is_resolved = 1,
                resolved_stock = (
                    SELECT quantity 
                    FROM temp_supplier_inventories 
                    WHERE temp_supplier_inventories.code = temp_local_inventories.code
                )
            WHERE EXISTS (
                SELECT 1 
                FROM temp_supplier_inventories 
                WHERE temp_supplier_inventories.code = temp_local_inventories.code
            )
        ');

        // 2. Auto-aprobar mediante la Base de Conocimiento (AliasDictionary)
        // Si ya vinculaste manualmente un código local con uno distinto del proveedor en el pasado, 
        // el sistema lo recuerda y transfiere el stock automáticamente.
        DB::statement('
            UPDATE temp_local_inventories
            SET is_resolved = 1,
                resolved_stock = (
                    SELECT temp_supplier_inventories.quantity 
                    FROM temp_supplier_inventories 
                    INNER JOIN alias_dictionaries ON alias_dictionaries.supplier_code = temp_supplier_inventories.code
                    WHERE alias_dictionaries.local_code = temp_local_inventories.code
                )
            WHERE EXISTS (
                SELECT 1 
                FROM temp_supplier_inventories 
                INNER JOIN alias_dictionaries ON alias_dictionaries.supplier_code = temp_supplier_inventories.code
                WHERE alias_dictionaries.local_code = temp_local_inventories.code
            )
        ');
    }

    /**
     * Auto-resuelve códigos con diferencia mínima (1 carácter) solo si hay un único candidato seguro.
     * Ejemplos típicos: DR5178-ENEL vs DR5178-ENELB, SP450-ENELBROC vs SP450-ENELBROCK.
     */
    protected function autoResolveNearCodeMatches(): void
    {
        $localPending = TempLocalInventory::query()
            ->where('is_resolved', false)
            ->get(['id', 'code']);

        if ($localPending->isEmpty()) {
            return;
        }

        $suppliers = TempSupplierInventory::query()
            ->get(['id', 'code', 'quantity']);

        if ($suppliers->isEmpty()) {
            return;
        }

        $supplierBuckets = [];

        foreach ($suppliers as $supplier) {
            $normalizedCode = $this->normalizeComparableCode((string) $supplier->code);

            if ($normalizedCode === '') {
                continue;
            }

            $bucket = substr($normalizedCode, 0, 6);

            $supplierBuckets[$bucket][] = [
                'id' => (int) $supplier->id,
                'code' => (string) $supplier->code,
                'quantity' => (int) $supplier->quantity,
                'normalized' => $normalizedCode,
                'digits' => $this->digitsSignature($normalizedCode),
            ];
        }

        $pendingResolutions = [];
        $usedSupplierCodes = [];

        foreach ($localPending as $local) {
            $localCode = (string) $local->code;
            $normalizedLocal = $this->normalizeComparableCode($localCode);

            if ($normalizedLocal === '') {
                continue;
            }

            $bucket = substr($normalizedLocal, 0, 6);
            $candidates = $supplierBuckets[$bucket] ?? [];

            if (empty($candidates)) {
                continue;
            }

            $localDigits = $this->digitsSignature($normalizedLocal);
            $nearMatches = [];

            foreach ($candidates as $candidate) {
                if (isset($usedSupplierCodes[$candidate['code']])) {
                    continue;
                }

                $lengthGap = abs(strlen($normalizedLocal) - strlen($candidate['normalized']));
                if ($lengthGap > 1) {
                    continue;
                }

                if ($localDigits !== '' && $candidate['digits'] !== '' && $localDigits !== $candidate['digits']) {
                    continue;
                }

                $distance = levenshtein($normalizedLocal, $candidate['normalized']);
                if ($distance > 1) {
                    continue;
                }

                $nearMatches[] = $candidate;
            }

            // Reglas de seguridad: solo resolvemos si hay exactamente un candidato.
            if (count($nearMatches) !== 1) {
                continue;
            }

            $selected = $nearMatches[0];

            $pendingResolutions[] = [
                'local_id' => (int) $local->id,
                'local_code' => $localCode,
                'supplier_code' => (string) $selected['code'],
                'supplier_quantity' => (int) $selected['quantity'],
            ];

            $usedSupplierCodes[(string) $selected['code']] = true;
        }

        if (empty($pendingResolutions)) {
            return;
        }

        DB::transaction(function () use ($pendingResolutions): void {
            foreach ($pendingResolutions as $resolution) {
                TempLocalInventory::query()
                    ->whereKey($resolution['local_id'])
                    ->update([
                        'is_resolved' => true,
                        'resolved_stock' => $resolution['supplier_quantity'],
                    ]);

                AliasDictionary::updateOrCreate([
                    'local_code' => $resolution['local_code'],
                    'supplier_code' => $resolution['supplier_code'],
                ]);
            }
        });
    }

    /**
     * Corrige falsos positivos de coincidencia exacta por código cuando la marca del local
     * sugiere que el match correcto es una variante extendida del código (ej: C1098-ENELBROCK).
     * Solo aplica cambios cuando hay evidencia fuerte y no ambigua.
     */
    protected function autoResolveBrandAwareExactConflicts(): void
    {
        $resolvedLocals = TempLocalInventory::query()
            ->where('is_resolved', true)
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->get(['id', 'code', 'brand']);

        if ($resolvedLocals->isEmpty()) {
            return;
        }

        $suppliers = TempSupplierInventory::query()
            ->get(['code', 'brand', 'quantity']);

        if ($suppliers->isEmpty()) {
            return;
        }

        $suppliersByCode = $suppliers->keyBy('code');
        $reassignments = [];
        $usedSupplierCodes = [];

        foreach ($resolvedLocals as $local) {
            $localCode = (string) $local->code;
            $localBrand = (string) $local->brand;

            if ($localCode === '' || $localBrand === '') {
                continue;
            }

            $exactSupplier = $suppliersByCode->get($localCode);
            if (!$exactSupplier) {
                continue;
            }

            // Si la marca coincide razonablemente, mantenemos la resolución exacta.
            if ($this->brandSimilarity($localBrand, (string) $exactSupplier->brand) >= 65) {
                continue;
            }

            $normalizedLocalCode = $this->normalizeComparableCode($localCode);
            $normalizedLocalBrand = $this->normalizeComparableCode($localBrand);

            if ($normalizedLocalCode === '' || $normalizedLocalBrand === '') {
                continue;
            }

            $scoredCandidates = $suppliers
                ->map(function (TempSupplierInventory $supplier) use ($localCode, $normalizedLocalCode, $normalizedLocalBrand, $localBrand) {
                    $supplierCode = (string) $supplier->code;
                    if ($supplierCode === '' || $supplierCode === $localCode) {
                        return null;
                    }

                    $normalizedSupplierCode = $this->normalizeComparableCode($supplierCode);
                    if ($normalizedSupplierCode === '' || !str_starts_with($normalizedSupplierCode, $normalizedLocalCode)) {
                        return null;
                    }

                    $brandScore = $this->brandSimilarity($localBrand, (string) $supplier->brand);
                    $codeContainsBrand = str_contains($normalizedSupplierCode, $normalizedLocalBrand);

                    if ($brandScore < 65 && !$codeContainsBrand) {
                        return null;
                    }

                    $score = $brandScore + ($codeContainsBrand ? 25 : 0);

                    return [
                        'supplier' => $supplier,
                        'score' => $score,
                    ];
                })
                ->filter()
                ->values()
                ->sortByDesc('score')
                ->values();

            if ($scoredCandidates->isEmpty()) {
                continue;
            }

            $top = $scoredCandidates->first();
            $runnerUp = $scoredCandidates->get(1);

            if (($top['score'] ?? 0) < 80) {
                continue;
            }

            if ($runnerUp !== null && (($top['score'] ?? 0) - ($runnerUp['score'] ?? 0)) < 10) {
                continue;
            }

            /** @var TempSupplierInventory $winner */
            $winner = $top['supplier'];
            $winnerCode = (string) $winner->code;

            if (isset($usedSupplierCodes[$winnerCode])) {
                continue;
            }

            $reassignments[] = [
                'local_id' => (int) $local->id,
                'local_code' => $localCode,
                'supplier_code' => $winnerCode,
                'supplier_quantity' => (int) $winner->quantity,
            ];

            $usedSupplierCodes[$winnerCode] = true;
        }

        if (empty($reassignments)) {
            return;
        }

        DB::transaction(function () use ($reassignments): void {
            foreach ($reassignments as $resolution) {
                TempLocalInventory::query()
                    ->whereKey($resolution['local_id'])
                    ->update([
                        'is_resolved' => true,
                        'resolved_stock' => $resolution['supplier_quantity'],
                    ]);

                // Mantener la relación 1-1 entre código local y código proveedor.
                AliasDictionary::query()->where('local_code', $resolution['local_code'])->delete();
                AliasDictionary::query()->where('supplier_code', $resolution['supplier_code'])->delete();

                AliasDictionary::query()->create([
                    'local_code' => $resolution['local_code'],
                    'supplier_code' => $resolution['supplier_code'],
                ]);
            }
        });
    }

    protected function brandSimilarity(?string $left, ?string $right): float
    {
        $normalizedLeft = $this->normalizeComparableCode((string) $left);
        $normalizedRight = $this->normalizeComparableCode((string) $right);

        if ($normalizedLeft === '' || $normalizedRight === '') {
            return 0.0;
        }

        similar_text($normalizedLeft, $normalizedRight, $percent);

        return $percent;
    }

    protected function normalizeComparableCode(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');
        $value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^A-Z0-9]+/', '', $value) ?? '';

        return trim($value);
    }

    protected function digitsSignature(string $normalizedCode): string
    {
        preg_match_all('/\d+/', $normalizedCode, $matches);

        return implode('-', $matches[0] ?? []);
    }

    protected function bumpReconciliationCacheVersion(): void
    {
        if (!Cache::has('reconciliation.sort.version')) {
            Cache::forever('reconciliation.sort.version', 1);
        }

        Cache::increment('reconciliation.sort.version');
    }
}