<?php

namespace App\Exports;

use App\Models\TempLocalInventory;
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
        protected int $lowStockThreshold = 5
    ) {
        // El umbral de bajo stock ahora se inyecta. 
        // Idealmente, al instanciar esta clase, le pasas config('inventory.low_stock_threshold')
    }

    public function collection(): Collection
    {
        $query = TempLocalInventory::query()
            ->where('is_resolved', true);

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('code', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->minimumStock !== null) {
            $query->where('resolved_stock', '<=', max(0, $this->minimumStock));
        }

        return $query
            ->orderBy('code', 'asc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Código Local',
            'Descripción del Producto',
            'Marca',
            'Stock Actualizado',
            'Estado',
        ];
    }

    public function map($product): array
    {
        $stock = (int) ($product->resolved_stock ?? 0);

        return [
            (string) $product->code,
            (string) ($product->description ?? '-'),
            (string) ($product->brand ?? '-'),
            $stock,
            $this->determineStockStatus($stock),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
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
    private function determineStockStatus(int $stock): string
    {
        if ($stock === 0) {
            return 'Agotado';
        }

        if ($stock <= $this->lowStockThreshold) {
            return 'Bajo Stock';
        }

        return 'Óptimo';
    }
}