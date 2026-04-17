<?php

namespace App\Services;

use App\Contracts\InventoryParserInterface;
use App\Imports\LocalInventoryImport;
use App\Imports\SupplierInventoryImport;
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

    protected function bumpReconciliationCacheVersion(): void
    {
        if (!Cache::has('reconciliation.sort.version')) {
            Cache::forever('reconciliation.sort.version', 1);
        }

        Cache::increment('reconciliation.sort.version');
    }
}