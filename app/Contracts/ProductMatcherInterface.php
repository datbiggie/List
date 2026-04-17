<?php

namespace App\Contracts;

use App\Models\TempLocalInventory;

interface ProductMatcherInterface
{
    /**
     * Construye el índice de búsqueda en SQLite.
     * Debe ser llamado después de importar el inventario del proveedor.
     */
    public function buildIndex(): void;

    /**
     * Busca la mejor coincidencia en el proveedor para un producto local.
     * Retorna un arreglo con los datos sugeridos o null si no hay coincidencias.
     */
    public function findBestMatch(TempLocalInventory $localProduct): ?array;
}