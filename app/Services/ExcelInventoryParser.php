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