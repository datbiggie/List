<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Exports\FinalInventoryExport;
use App\Models\TempLocalInventory;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinalInventoryTable extends Component
{
    use WithPagination;

    // Propiedad para el buscador en tiempo real
    public string $search = '';

    // Umbral mínimo requerido por el usuario para detectar bajo stock
    public ?int $minimumStock = null;

    // Reseteamos la paginación si el usuario escribe en el buscador
    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingMinimumStock()
    {
        $this->resetPage();
    }

    // Método preparado para la futura exportación (SRP)
    public function exportToExcel(): BinaryFileResponse
    {
        return Excel::download(
            new FinalInventoryExport($this->search, $this->minimumStock),
            'inventario_actualizado_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function render()
    {
        $query = $this->baseQuery();

        // Ordenamos alfabéticamente por defecto
        $products = $query->orderBy('code', 'asc')->paginate(15);

        return view('livewire.final-inventory-table', [
            'products' => $products
        ]);
    }

    protected function baseQuery()
    {
        $query = TempLocalInventory::query()
            ->where('is_resolved', true); // Solo mostramos lo ya conciliado

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('code', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->minimumStock !== null && $this->minimumStock !== '') {
            $query->where('resolved_stock', '<=', max(0, (int) $this->minimumStock));
        }

        return $query;
    }
}