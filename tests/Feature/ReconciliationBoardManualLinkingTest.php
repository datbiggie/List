<?php

namespace Tests\Feature;

use App\Contracts\ProductMatcherInterface;
use App\Livewire\ReconciliationBoard;
use App\Models\AliasDictionary;
use App\Models\TempLocalInventory;
use App\Models\TempSupplierInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReconciliationBoardManualLinkingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(ProductMatcherInterface::class, FakeMatcher::class);
    }

    public function test_manual_search_shows_supplier_code_even_if_it_is_already_linked_elsewhere(): void
    {
        $localToFix = TempLocalInventory::create([
            'code' => 'TR-7 LBS',
            'description' => 'Producto local a corregir',
            'brand' => 'ENELB',
        ]);

        TempLocalInventory::create([
            'code' => 'OTRO-CODIGO',
            'description' => 'Otro producto local',
            'brand' => 'ENELB',
        ]);

        $supplierCorrect = TempSupplierInventory::create([
            'code' => 'TR-7LBS-ENELB',
            'description' => 'Producto proveedor correcto',
            'brand' => 'ENELB',
            'quantity' => 11,
        ]);

        AliasDictionary::create([
            'local_code' => 'OTRO-CODIGO',
            'supplier_code' => $supplierCorrect->code,
        ]);

        $test = Livewire::test(ReconciliationBoard::class)
            ->set("manualQueries.{$localToFix->id}", 'TR-7LBS-ENELB');

        $candidates = $test->get("manualCandidates.{$localToFix->id}");

        $this->assertIsArray($candidates);
        $this->assertContains('TR-7LBS-ENELB', array_column($candidates, 'code'));
    }

    public function test_approve_match_reassigns_aliases_to_fix_wrong_links(): void
    {
        $localToFix = TempLocalInventory::create([
            'code' => 'TR-7 LBS',
            'description' => 'Producto local a corregir',
            'brand' => 'ENELB',
            'is_resolved' => false,
        ]);

        TempLocalInventory::create([
            'code' => 'OTRO-CODIGO',
            'description' => 'Otro producto local',
            'brand' => 'ENELB',
            'is_resolved' => false,
        ]);

        $wrongSupplier = TempSupplierInventory::create([
            'code' => 'TR-9 LBS',
            'description' => 'Proveedor incorrecto',
            'brand' => 'ENELB',
            'quantity' => 3,
        ]);

        $correctSupplier = TempSupplierInventory::create([
            'code' => 'TR-7LBS-ENELB',
            'description' => 'Proveedor correcto',
            'brand' => 'ENELB',
            'quantity' => 19,
        ]);

        AliasDictionary::create([
            'local_code' => 'TR-7 LBS',
            'supplier_code' => $wrongSupplier->code,
        ]);

        AliasDictionary::create([
            'local_code' => 'OTRO-CODIGO',
            'supplier_code' => $correctSupplier->code,
        ]);

        Livewire::test(ReconciliationBoard::class)
            ->call('approveMatch', $localToFix->id, $correctSupplier->id);

        $this->assertDatabaseHas('alias_dictionaries', [
            'local_code' => 'TR-7 LBS',
            'supplier_code' => 'TR-7LBS-ENELB',
        ]);

        $this->assertDatabaseMissing('alias_dictionaries', [
            'local_code' => 'TR-7 LBS',
            'supplier_code' => 'TR-9 LBS',
        ]);

        $this->assertDatabaseMissing('alias_dictionaries', [
            'local_code' => 'OTRO-CODIGO',
            'supplier_code' => 'TR-7LBS-ENELB',
        ]);

        $localToFix->refresh();

        $this->assertTrue((bool) $localToFix->is_resolved);
        $this->assertSame(19, (int) $localToFix->resolved_stock);
    }

    public function test_manual_search_keeps_exact_supplier_code_visible_with_many_generic_matches(): void
    {
        $localToFix = TempLocalInventory::create([
            'code' => 'TR-7 LBS',
            'description' => 'Producto local a corregir',
            'brand' => 'ENELB',
        ]);

        for ($i = 1; $i <= 60; $i++) {
            TempSupplierInventory::create([
                'code' => 'TR-' . $i . '-ENELB',
                'description' => 'Coincidencia genérica ' . $i,
                'brand' => 'ENELB',
                'quantity' => $i,
            ]);
        }

        TempSupplierInventory::create([
            'code' => 'TR-7LBS-ENELB',
            'description' => 'Producto correcto exacto',
            'brand' => 'ENELB',
            'quantity' => 77,
        ]);

        $test = Livewire::test(ReconciliationBoard::class)
            ->set("manualQueries.{$localToFix->id}", 'TR-7LBS-ENELB');

        $candidates = $test->get("manualCandidates.{$localToFix->id}");

        $this->assertIsArray($candidates);
        $this->assertContains('TR-7LBS-ENELB', array_column($candidates, 'code'));
    }
}

class FakeMatcher implements ProductMatcherInterface
{
    public function buildIndex(): void
    {
        // No-op para pruebas del board de conciliacion.
    }

    public function findBestMatch(TempLocalInventory $localProduct): ?array
    {
        return null;
    }
}
