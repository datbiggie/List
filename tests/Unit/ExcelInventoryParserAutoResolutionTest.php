<?php

namespace Tests\Unit;

use App\Models\AliasDictionary;
use App\Models\TempLocalInventory;
use App\Models\TempSupplierInventory;
use App\Services\ExcelInventoryParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExcelInventoryParserAutoResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reassigns_exact_code_resolution_when_local_brand_matches_a_qualified_supplier_code(): void
    {
        TempLocalInventory::create([
            'code' => 'C1098',
            'description' => 'Producto local',
            'brand' => 'ENELBROCK',
            'is_resolved' => false,
        ]);

        TempSupplierInventory::create([
            'code' => 'C1098',
            'description' => 'Producto proveedor base',
            'brand' => 'PORTER',
            'quantity' => 1,
        ]);

        TempSupplierInventory::create([
            'code' => 'C1098-ENELBROCK',
            'description' => 'Producto proveedor marca correcta',
            'brand' => 'ENELBROCK',
            'quantity' => 14,
        ]);

        $parser = new ExcelInventoryParserProbe();

        $parser->runAutoResolveExactMatches();
        $parser->runAutoResolveBrandAwareExactConflicts();

        $local = TempLocalInventory::query()->where('code', 'C1098')->firstOrFail();

        $this->assertTrue((bool) $local->is_resolved);
        $this->assertSame(14, (int) $local->resolved_stock);

        $this->assertDatabaseHas('alias_dictionaries', [
            'local_code' => 'C1098',
            'supplier_code' => 'C1098-ENELBROCK',
        ]);
    }

    public function test_it_does_not_reassign_when_multiple_brand_qualified_candidates_are_ambiguous(): void
    {
        TempLocalInventory::create([
            'code' => 'C1098',
            'description' => 'Producto local',
            'brand' => 'ENELBROCK',
            'is_resolved' => false,
        ]);

        TempSupplierInventory::create([
            'code' => 'C1098',
            'description' => 'Producto proveedor base',
            'brand' => 'PORTER',
            'quantity' => 1,
        ]);

        TempSupplierInventory::create([
            'code' => 'C1098-ENELBROCK-A',
            'description' => 'Variante A',
            'brand' => 'ENELBROCK',
            'quantity' => 7,
        ]);

        TempSupplierInventory::create([
            'code' => 'C1098-ENELBROCK-B',
            'description' => 'Variante B',
            'brand' => 'ENELBROCK',
            'quantity' => 9,
        ]);

        $parser = new ExcelInventoryParserProbe();

        $parser->runAutoResolveExactMatches();
        $parser->runAutoResolveBrandAwareExactConflicts();

        $local = TempLocalInventory::query()->where('code', 'C1098')->firstOrFail();

        // Se conserva el match exacto original por falta de evidencia unica.
        $this->assertSame(1, (int) $local->resolved_stock);

        $this->assertDatabaseMissing('alias_dictionaries', [
            'local_code' => 'C1098',
            'supplier_code' => 'C1098-ENELBROCK-A',
        ]);

        $this->assertDatabaseMissing('alias_dictionaries', [
            'local_code' => 'C1098',
            'supplier_code' => 'C1098-ENELBROCK-B',
        ]);
    }
}

class ExcelInventoryParserProbe extends ExcelInventoryParser
{
    public function runAutoResolveExactMatches(): void
    {
        $this->autoResolveExactMatches();
    }

    public function runAutoResolveBrandAwareExactConflicts(): void
    {
        $this->autoResolveBrandAwareExactConflicts();
    }
}
