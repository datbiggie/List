<?php

namespace Tests\Unit;

use App\Imports\LocalInventoryImport;
use App\Imports\SupplierInventoryImport;
use App\Models\TempSupplierInventory;
use Tests\TestCase;

class SupplierInventoryImportTest extends TestCase
{
    public function test_it_skips_title_rows_like_lista_de_precios(): void
    {
        $import = new SupplierInventoryImport();

        $result = $import->model([
            'codigo' => 'Lista de precios',
            'descripcion' => '',
            'marca' => '',
            'cant' => '',
        ]);

        $this->assertNull($result);
    }

    public function test_it_builds_a_supplier_inventory_model_for_real_rows(): void
    {
        $import = new SupplierInventoryImport();

        $result = $import->model([
            'codigo' => '001',
            'descripcion' => 'Tornillo galvanizado',
            'marca' => 'ACME',
            'cant' => '12',
        ]);

        $this->assertInstanceOf(TempSupplierInventory::class, $result);
        $this->assertSame('001', $result->code);
        $this->assertSame('Tornillo galvanizado', $result->description);
        $this->assertSame('ACME', $result->brand);
        $this->assertSame(12, $result->quantity);
    }

    public function test_it_skips_title_rows_in_the_local_inventory_import_too(): void
    {
        $import = new LocalInventoryImport();

        $result = $import->model([
            'codigo' => 'Lista de precios',
            'descripción' => '',
            'marca' => '',
        ]);

        $this->assertNull($result);
    }
}
