<?php

namespace Tests\Feature;

use App\Livewire\FinalInventoryTable;
use App\Models\AliasDictionary;
use App\Models\TempLocalInventory;
use App\Models\TempSupplierInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinalInventoryTableVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_includes_pending_products_as_possible_out_of_stock_by_default(): void
    {
        TempLocalInventory::create([
            'code' => 'RES-001',
            'description' => 'Resuelto',
            'is_resolved' => true,
            'resolved_stock' => 8,
        ]);

        TempLocalInventory::create([
            'code' => 'PEND-001',
            'description' => 'Pendiente',
            'is_resolved' => false,
        ]);

        Livewire::test(FinalInventoryTable::class)
            ->assertSee('RES-001')
            ->assertSee('PEND-001')
            ->assertSee('Posible agotado');
    }

    public function test_it_can_hide_pending_products_when_toggle_is_disabled(): void
    {
        TempLocalInventory::create([
            'code' => 'RES-001',
            'description' => 'Resuelto',
            'is_resolved' => true,
            'resolved_stock' => 8,
        ]);

        TempLocalInventory::create([
            'code' => 'PEND-001',
            'description' => 'Pendiente',
            'is_resolved' => false,
        ]);

        Livewire::test(FinalInventoryTable::class)
            ->set('includePendingAsOutOfStock', false)
            ->assertSee('RES-001')
            ->assertDontSee('PEND-001');
    }

    public function test_it_uses_only_supplier_brands_for_brand_filter_options(): void
    {
        TempSupplierInventory::create([
            'code' => 'SUP-ACME',
            'description' => 'Proveedor ACME',
            'brand' => 'ACME SUP',
            'quantity' => 3,
        ]);

        TempLocalInventory::create([
            'code' => 'LOC-ACME',
            'description' => 'Producto ACME',
            'brand' => 'LOCAL ONLY BRAND',
            'is_resolved' => true,
            'resolved_stock' => 5,
        ]);

        Livewire::test(FinalInventoryTable::class)
            ->assertSee('ACME SUP')
            ->assertDontSee('No hay marcas de proveedor disponibles.');
    }

    public function test_it_hides_selected_supplier_brands_using_aliases_and_exact_code_matches(): void
    {
        TempSupplierInventory::create([
            'code' => 'SUP-ACME-01',
            'description' => 'Proveedor ACME',
            'brand' => 'ACME SUP',
            'quantity' => 9,
        ]);

        TempSupplierInventory::create([
            'code' => 'SUP-BETA-01',
            'description' => 'Proveedor BETA',
            'brand' => 'BETA SUP',
            'quantity' => 4,
        ]);

        TempLocalInventory::create([
            'code' => 'LOCAL-A',
            'description' => 'Local vinculado por alias a ACME',
            'brand' => 'MARCA LOCAL',
            'is_resolved' => true,
            'resolved_stock' => 9,
        ]);

        TempLocalInventory::create([
            'code' => 'SUP-BETA-01',
            'description' => 'Local con match exacto por código a BETA',
            'brand' => 'OTRA LOCAL',
            'is_resolved' => true,
            'resolved_stock' => 4,
        ]);

        AliasDictionary::create([
            'local_code' => 'LOCAL-A',
            'supplier_code' => 'SUP-ACME-01',
        ]);

        Livewire::test(FinalInventoryTable::class)
            ->set('selectedBrands', ['ACME SUP'])
            ->assertDontSee('LOCAL-A')
            ->assertSee('SUP-BETA-01');
    }

    public function test_it_displays_supplier_brand_instead_of_local_brand_for_exact_code_match(): void
    {
        TempSupplierInventory::create([
            'code' => 'C1098',
            'description' => 'Proveedor C1098',
            'brand' => 'PORTER',
            'quantity' => 1,
        ]);

        TempSupplierInventory::create([
            'code' => 'C1098-ENELBROCK',
            'description' => 'Proveedor C1098 variante',
            'brand' => 'ENELBROCK',
            'quantity' => 10,
        ]);

        TempLocalInventory::create([
            'code' => 'C1098',
            'description' => 'Local C1098',
            'brand' => 'LOCAL-ENELBROCK',
            'is_resolved' => true,
            'resolved_stock' => 1,
        ]);

        Livewire::test(FinalInventoryTable::class)
            ->assertSee('C1098')
            ->assertSee('PORTER')
            ->assertSee('Local: LOCAL-ENELBROCK');
    }

    public function test_it_hides_selected_local_brands_from_table_results(): void
    {
        TempSupplierInventory::create([
            'code' => 'LOC-001',
            'brand' => 'SUP-A',
            'quantity' => 2,
        ]);

        TempSupplierInventory::create([
            'code' => 'LOC-002',
            'brand' => 'SUP-B',
            'quantity' => 2,
        ]);

        TempLocalInventory::create([
            'code' => 'LOC-001',
            'description' => 'Marca local ocultable',
            'brand' => 'NO-PROVEEDOR',
            'is_resolved' => false,
        ]);

        TempLocalInventory::create([
            'code' => 'LOC-002',
            'description' => 'Marca local visible',
            'brand' => 'ACTIVA',
            'is_resolved' => false,
        ]);

        Livewire::test(FinalInventoryTable::class)
            ->set('selectedLocalBrands', ['NO-PROVEEDOR'])
            ->assertDontSee('LOC-001')
            ->assertSee('LOC-002');
    }
}
