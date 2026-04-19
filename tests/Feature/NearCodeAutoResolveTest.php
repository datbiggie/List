<?php

namespace Tests\Feature;

use App\Models\AliasDictionary;
use App\Models\TempLocalInventory;
use App\Models\TempSupplierInventory;
use App\Services\ExcelInventoryParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NearCodeAutoResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_auto_resolves_unique_one_letter_code_variants(): void
    {
        $localA = TempLocalInventory::create(['code' => 'DR5178-ENEL']);
        $localB = TempLocalInventory::create(['code' => 'SP450-ENELBROC']);
        $localC = TempLocalInventory::create(['code' => '5510S-ENELBROC']);

        TempSupplierInventory::create([
            'code' => 'DR5178-ENELB',
            'quantity' => 12,
        ]);

        TempSupplierInventory::create([
            'code' => 'SP450-ENELBROCK',
            'quantity' => 9,
        ]);

        TempSupplierInventory::create([
            'code' => '5510S-ENELBROCK',
            'quantity' => 4,
        ]);

        $parser = new class extends ExcelInventoryParser {
            public function runNearCodeResolution(): void
            {
                $this->autoResolveNearCodeMatches();
            }
        };

        $parser->runNearCodeResolution();

        $this->assertTrue((bool) $localA->fresh()->is_resolved);
        $this->assertTrue((bool) $localB->fresh()->is_resolved);
        $this->assertTrue((bool) $localC->fresh()->is_resolved);

        $this->assertSame(12, (int) $localA->fresh()->resolved_stock);
        $this->assertSame(9, (int) $localB->fresh()->resolved_stock);
        $this->assertSame(4, (int) $localC->fresh()->resolved_stock);

        $this->assertDatabaseHas('alias_dictionaries', [
            'local_code' => 'DR5178-ENEL',
            'supplier_code' => 'DR5178-ENELB',
        ]);

        $this->assertDatabaseHas('alias_dictionaries', [
            'local_code' => 'SP450-ENELBROC',
            'supplier_code' => 'SP450-ENELBROCK',
        ]);

        $this->assertDatabaseHas('alias_dictionaries', [
            'local_code' => '5510S-ENELBROC',
            'supplier_code' => '5510S-ENELBROCK',
        ]);
    }

    public function test_it_keeps_local_product_pending_when_near_match_is_ambiguous(): void
    {
        $local = TempLocalInventory::create(['code' => 'AB100-ENELBROC']);

        TempSupplierInventory::create([
            'code' => 'AB100-ENELBROCK',
            'quantity' => 8,
        ]);

        TempSupplierInventory::create([
            'code' => 'AB100-ENELBROCA',
            'quantity' => 10,
        ]);

        $parser = new class extends ExcelInventoryParser {
            public function runNearCodeResolution(): void
            {
                $this->autoResolveNearCodeMatches();
            }
        };

        $parser->runNearCodeResolution();

        $this->assertFalse((bool) $local->fresh()->is_resolved);

        $this->assertDatabaseMissing('alias_dictionaries', [
            'local_code' => 'AB100-ENELBROC',
            'supplier_code' => 'AB100-ENELBROCK',
        ]);

        $this->assertDatabaseMissing('alias_dictionaries', [
            'local_code' => 'AB100-ENELBROC',
            'supplier_code' => 'AB100-ENELBROCA',
        ]);

        $this->assertSame(0, AliasDictionary::query()->count());
    }
}
