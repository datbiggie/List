<?php

namespace App\Exports;

use App\Models\AliasDictionary;
use App\Models\TempLocalInventory;
use App\Models\TempSupplierInventory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinalInventoryExport implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithColumnFormatting,
    ShouldAutoSize,
    WithStyles
{
    public function __construct(
        protected string $search = '',
        protected ?int $minimumStock = null,
        protected bool $includePendingAsOutOfStock = true,
        protected array $selectedBrands = [],
        protected array $selectedLocalBrands = [],
        protected int $lowStockThreshold = 5
    ) {
        // El umbral de bajo stock ahora se inyecta. 
        // Idealmente, al instanciar esta clase, le pasas config('inventory.low_stock_threshold')
    }

    public function collection(): Collection
    {
        $query = TempLocalInventory::query();

        if (!$this->includePendingAsOutOfStock) {
            $query->where('is_resolved', true);
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('code', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if (!empty($this->selectedBrands)) {
            $supplierCodesByBrand = TempSupplierInventory::query()
                ->select('code')
                ->whereIn('brand', $this->selectedBrands)
                ->whereNotNull('brand')
                ->where('brand', '!=', '');

            $query->where(function ($brandQuery) use ($supplierCodesByBrand) {
                $brandQuery
                    ->whereNotIn('code', $supplierCodesByBrand)
                    ->whereNotIn('code', AliasDictionary::query()
                        ->select('local_code')
                        ->whereIn('supplier_code', clone $supplierCodesByBrand)
                    );
            });
        }

        if (!empty($this->selectedLocalBrands)) {
            $query->where(function ($localBrandQuery) {
                $localBrandQuery
                    ->whereNull('brand')
                    ->orWhere('brand', '')
                    ->orWhereNotIn('brand', $this->selectedLocalBrands);
            });
        }

        if ($this->minimumStock !== null) {
            $threshold = max(0, $this->minimumStock);

            if ($this->includePendingAsOutOfStock) {
                $query->where(function ($q) use ($threshold) {
                    $q->where(function ($resolvedQuery) use ($threshold) {
                        $resolvedQuery
                            ->where('is_resolved', true)
                            ->where('resolved_stock', '<=', $threshold);
                    })->orWhere('is_resolved', false);
                });
            } else {
                $query->where('resolved_stock', '<=', $threshold);
            }
        }

        $products = $query
            ->orderBy('code', 'asc')
            ->get();

        return $this->attachSupplierMetadata($products);
    }

    public function headings(): array
    {
        return [
            'Código Local',
            'Código Proveedor',
            'Descripción del Producto',
            'Marca Proveedor',
            'Marca Local',
            'Stock Actualizado',
            'Estado',
        ];
    }

    public function map($product): array
    {
        $stock = (int) ($product->is_resolved ? ($product->resolved_stock ?? 0) : 0);

        return [
            (string) $product->code,
            (string) ($product->supplier_code ?? $product->code),
            (string) ($product->description ?? '-'),
            (string) ($product->supplier_brand ?? '-'),
            (string) ($product->brand ?? '-'),
            $stock,
            $this->determineStockStatus($stock, (bool) $product->is_resolved),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Aplica negrita a la primera fila (encabezados) de forma estándar
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Extraemos la lógica de estado a un método privado para aplicar Single Responsibility Principle (SRP)
     */
    private function determineStockStatus(int $stock, bool $isResolved): string
    {
        if (!$isResolved) {
            return 'Posible agotado';
        }

        if ($stock === 0) {
            return 'Agotado';
        }

        if ($stock <= $this->lowStockThreshold) {
            return 'Bajo Stock';
        }

        return 'Óptimo';
    }

    private function attachSupplierMetadata(Collection $products): Collection
    {
        if ($products->isEmpty()) {
            return $products;
        }

        $localCodes = $products
            ->pluck('code')
            ->filter(fn ($code) => is_string($code) && $code !== '')
            ->unique()
            ->values();

        if ($localCodes->isEmpty()) {
            return $products;
        }

        $aliasByLocalCode = AliasDictionary::query()
            ->whereIn('local_code', $localCodes->all())
            ->pluck('supplier_code', 'local_code');

        $supplierCodes = $localCodes
            ->merge($aliasByLocalCode->values())
            ->filter(fn ($code) => is_string($code) && $code !== '')
            ->unique()
            ->values();

        $suppliersByCode = TempSupplierInventory::query()
            ->whereIn('code', $supplierCodes->all())
            ->get(['code', 'brand'])
            ->keyBy('code');

        return $products->map(function (TempLocalInventory $product) use ($aliasByLocalCode, $suppliersByCode) {
            $supplierCode = $aliasByLocalCode->get($product->code, $product->code);
            $supplier = $suppliersByCode->get($supplierCode);

            $product->setAttribute('supplier_code', $supplierCode);
            $product->setAttribute('supplier_brand', $supplier?->brand);

            return $product;
        });
    }
}