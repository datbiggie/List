<?php

namespace Tests\Unit;

use App\Exports\FinalInventoryExport;
use App\Models\AliasDictionary;
use App\Models\TempLocalInventory;
use App\Models\TempSupplierInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalInventoryExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_supplier_and_local_brand_columns_with_expected_statuses(): void
    {
        TempSupplierInventory::create([
            'code' => 'C1098',
            'brand' => 'PORTER',
            'quantity' => 1,
        ]);

        TempSupplierInventory::create([
            'code' => 'SUP-ALIAS',
            'brand' => 'ALIAS-BRAND',
            'quantity' => 2,
        ]);

        AliasDictionary::create([
            'local_code' => 'LOC-ALIAS',
            'supplier_code' => 'SUP-ALIAS',
        ]);

        TempLocalInventory::create([
            'code' => 'C1098',
            'description' => 'Local exacto',
            'brand' => 'LOCAL-ENELBROCK',
            'is_resolved' => true,
            'resolved_stock' => 1,
        ]);

        TempLocalInventory::create([
            'code' => 'LOC-ALIAS',
            'description' => 'Local por alias',
            'brand' => 'LOCAL-ALIAS',
            'is_resolved' => true,
            'resolved_stock' => 2,
        ]);

        TempLocalInventory::create([
            'code' => 'PEND-001',
            'description' => 'Pendiente',
            'brand' => 'LOCAL-PEND',
            'is_resolved' => false,
            'resolved_stock' => null,
        ]);

        $export = new FinalInventoryExport('', null, true, [], []);

        $this->assertSame([
            'Código Local',
            'Código Proveedor',
            'Descripción del Producto',
            'Marca Proveedor',
            'Marca Local',
            'Stock Actualizado',
            'Estado',
        ], $export->headings());

        $rows = $export->collection()->values();

        $exact = $rows->firstWhere('code', 'C1098');
        $this->assertNotNull($exact);
        $mappedExact = $export->map($exact);
        $this->assertSame('C1098', $mappedExact[0]);
        $this->assertSame('C1098', $mappedExact[1]);
        $this->assertSame('PORTER', $mappedExact[3]);
        $this->assertSame('LOCAL-ENELBROCK', $mappedExact[4]);

        $alias = $rows->firstWhere('code', 'LOC-ALIAS');
        $this->assertNotNull($alias);
        $mappedAlias = $export->map($alias);
        $this->assertSame('SUP-ALIAS', $mappedAlias[1]);
        $this->assertSame('ALIAS-BRAND', $mappedAlias[3]);

        $pending = $rows->firstWhere('code', 'PEND-001');
        $this->assertNotNull($pending);
        $mappedPending = $export->map($pending);
        $this->assertSame(0, $mappedPending[5]);
        $this->assertSame('Posible agotado', $mappedPending[6]);
    }

    public function test_it_hides_selected_supplier_brands_and_applies_stock_rules_like_table(): void
    {
        TempSupplierInventory::create([
            'code' => 'SUP-PORTER',
            'brand' => 'PORTER',
            'quantity' => 1,
        ]);

        TempSupplierInventory::create([
            'code' => 'SUP-OTHER',
            'brand' => 'OTHER',
            'quantity' => 8,
        ]);

        AliasDictionary::create([
            'local_code' => 'LOC-PORTER',
            'supplier_code' => 'SUP-PORTER',
        ]);

        TempLocalInventory::create([
            'code' => 'LOC-PORTER',
            'description' => 'Local Porter',
            'brand' => 'LOCAL-A',
            'is_resolved' => true,
            'resolved_stock' => 1,
        ]);

        TempLocalInventory::create([
            'code' => 'SUP-OTHER',
            'description' => 'Local Other',
            'brand' => 'LOCAL-B',
            'is_resolved' => true,
            'resolved_stock' => 8,
        ]);

        TempLocalInventory::create([
            'code' => 'PEND-X',
            'description' => 'Pendiente X',
            'brand' => 'LOCAL-P',
            'is_resolved' => false,
        ]);

        $export = new FinalInventoryExport('', 2, true, ['PORTER'], []);

        $codes = $export->collection()->pluck('code')->all();

        $this->assertNotContains('LOC-PORTER', $codes);
        $this->assertContains('PEND-X', $codes);
        $this->assertNotContains('SUP-OTHER', $codes);
    }

    public function test_it_hides_selected_local_brands_in_export(): void
    {
        TempSupplierInventory::create([
            'code' => 'LOC-A',
            'brand' => 'SUP-A',
            'quantity' => 3,
        ]);

        TempSupplierInventory::create([
            'code' => 'LOC-B',
            'brand' => 'SUP-B',
            'quantity' => 3,
        ]);

        TempLocalInventory::create([
            'code' => 'LOC-A',
            'description' => 'Producto A',
            'brand' => 'NO-PROVEEDOR',
            'is_resolved' => false,
        ]);

        TempLocalInventory::create([
            'code' => 'LOC-B',
            'description' => 'Producto B',
            'brand' => 'VALIDA',
            'is_resolved' => false,
        ]);

        $export = new FinalInventoryExport('', null, true, [], ['NO-PROVEEDOR']);
        $codes = $export->collection()->pluck('code')->all();

        $this->assertNotContains('LOC-A', $codes);
        $this->assertContains('LOC-B', $codes);
    }
}
