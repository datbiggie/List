<?php

namespace App\Contracts;

interface InventoryParserInterface
{
    /**
     * Procesa el archivo de inventario local y llena temp_local_inventories.
     */
    public function parseLocal(string $filePath): void;

    /**
     * Procesa el archivo del proveedor y llena temp_supplier_inventories.
     */
    public function parseSupplier(string $filePath): void;
}