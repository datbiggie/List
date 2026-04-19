<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Exports\FinalInventoryExport;
use App\Models\AliasDictionary;
use App\Models\TempLocalInventory;
use App\Models\TempSupplierInventory;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinalInventoryTable extends Component
{
    use WithPagination;

    // Propiedad para el buscador en tiempo real
    public string $search = '';

    // Umbral mínimo requerido por el usuario para detectar bajo stock
    public ?int $minimumStock = null;

    // Permite visualizar productos pendientes como posible agotado (stock 0)
    public bool $includePendingAsOutOfStock = true;

    // Filtro multiselección de marcas
    public array $selectedBrands = [];

    // Filtro de marcas locales a ocultar
    public array $selectedLocalBrands = [];

    // Reseteamos la paginación si el usuario escribe en el buscador
    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingMinimumStock()
    {
        $this->resetPage();
    }

    public function updatingIncludePendingAsOutOfStock()
    {
        $this->resetPage();
    }

    public function updatingSelectedBrands(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedLocalBrands(): void
    {
        $this->resetPage();
    }

    // Método preparado para la futura exportación (SRP)
    public function exportToExcel(): BinaryFileResponse
    {
        return Excel::download(
            new FinalInventoryExport(
                $this->search,
                $this->minimumStock,
                $this->includePendingAsOutOfStock,
                $this->selectedBrands,
                $this->selectedLocalBrands
            ),
            'inventario_actualizado_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function render()
    {
        $availableBrands = $this->availableBrands();
        $availableLocalBrands = $this->availableLocalBrands();

        $this->selectedBrands = array_values(array_intersect($this->selectedBrands, $availableBrands));
        $this->selectedLocalBrands = array_values(array_intersect($this->selectedLocalBrands, $availableLocalBrands));

        $query = $this->baseQuery();

        // Ordenamos alfabéticamente por defecto
        $products = $query->orderBy('code', 'asc')->paginate(20);
        $this->attachSupplierMetadata($products);

        return view('livewire.final-inventory-table', [
            'products' => $products,
            'availableBrands' => $availableBrands,
            'availableLocalBrands' => $availableLocalBrands,
        ]);
    }

    protected function baseQuery()
    {
        $query = TempLocalInventory::query();

        if (!$this->includePendingAsOutOfStock) {
            $query->where('is_resolved', true); // Solo mostramos lo ya conciliado
        }

        if (!empty($this->search)) {
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

        if ($this->minimumStock !== null && $this->minimumStock !== '') {
            $threshold = max(0, (int) $this->minimumStock);

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

        return $query;
    }

    protected function availableBrands(): array
    {
        return TempSupplierInventory::query()
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->values()
            ->all();
    }

    protected function availableLocalBrands(): array
    {
        return TempLocalInventory::query()
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->values()
            ->all();
    }

    protected function attachSupplierMetadata($products): void
    {
        $collection = $products->getCollection();

        if ($collection->isEmpty()) {
            return;
        }

        $localCodes = $collection
            ->pluck('code')
            ->filter(fn ($code) => is_string($code) && $code !== '')
            ->unique()
            ->values();

        if ($localCodes->isEmpty()) {
            return;
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

        $products->setCollection(
            $collection->map(function (TempLocalInventory $product) use ($aliasByLocalCode, $suppliersByCode) {
                $supplierCode = $aliasByLocalCode->get($product->code, $product->code);
                $supplier = $suppliersByCode->get($supplierCode);

                $product->setAttribute('supplier_code', $supplierCode);
                $product->setAttribute('supplier_brand', $supplier?->brand);

                return $product;
            })
        );
    }
}