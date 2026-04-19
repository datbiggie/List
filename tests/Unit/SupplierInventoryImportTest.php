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

    public function test_it_reads_brand_from_alternative_brand_heading(): void
    {
        $import = new SupplierInventoryImport();

        $result = $import->model([
            'codigo' => 'A-77',
            'descripcion' => 'Valvula de prueba',
            'marca_producto' => 'BRANDX',
            'cant' => '5',
        ]);

        $this->assertInstanceOf(TempSupplierInventory::class, $result);
        $this->assertSame('BRANDX', $result->brand);
    }

    public function test_it_reads_brand_from_unnamed_column_when_marca_key_is_empty(): void
    {
        $import = new SupplierInventoryImport();

        $result = $import->model([
            'codigo' => '8483N-3P-ENELB',
            'descripcion' => 'ALTERNADOR AVEO 1.6L',
            2 => '',
            3 => 'ENELBROCK',
            'marca' => '',
            'cant' => '56',
            'precio_bs' => 'Bs.61.170,68',
            7 => '127.5',
            'precio' => '',
            9 => '',
        ]);

        $this->assertInstanceOf(TempSupplierInventory::class, $result);
        $this->assertSame('ENELBROCK', $result->brand);
    }

    public function test_it_does_not_throw_missing_code_header_when_code_cell_is_empty(): void
    {
        $import = new SupplierInventoryImport();

        $result = $import->model([
            'codigo' => '',
            'descripcion' => 'Fila vacia sin codigo',
            'marca' => 'ACME',
            'cant' => '3',
        ]);

        $this->assertNull($result);
    }
}
