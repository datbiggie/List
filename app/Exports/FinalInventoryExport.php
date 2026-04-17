<?php

namespace App\Exports;

use App\Models\TempLocalInventory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class FinalInventoryExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting
{
    public function __construct(
        protected string $search = '',
        protected ?int $minimumStock = null,
    ) {
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
            $stock === 0 ? 'Agotado' : ($stock <= 5 ? 'Bajo Stock' : 'Óptimo'),
        ];
    }

    public function columnFormats(): array
    {
        return [
            // Forzamos texto para no perder ceros a la izquierda en Excel.
            'A' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
