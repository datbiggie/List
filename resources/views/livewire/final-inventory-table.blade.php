<div class="max-w-7xl mx-auto px-4 md:px-8 pt-8 pb-24 font-sans selection:bg-green-100 selection:text-slate-900">
    
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:justify-between md:items-end">
        <div>
            <h2 class="text-slate-800 font-bold text-2xl md:text-3xl mb-1">Inventario Actualizado</h2>
            <p class="text-gray-600 text-sm md:text-base">Lista maestra de productos con el stock conciliado del proveedor.</p>
        </div>
        
        <div class="flex flex-wrap gap-3 items-center">
            <div class="relative">
                <input 
                    wire:model.live.debounce.300ms="search" 
                    type="text" 
                    placeholder="Buscar código o descripción..." 
                    class="bg-white text-slate-800 border border-gray-300 rounded-md px-4 h-10 text-sm w-70 focus:outline-none focus:ring-2 focus:ring-green-700 focus:border-green-700 transition-all placeholder:text-gray-400"
                >
            </div>

            <div class="relative">
                <input
                    wire:model.live.debounce.300ms="minimumStock"
                    type="number"
                    min="0"
                    step="1"
                    placeholder="Stock mínimo"
                    class="bg-white text-slate-800 border border-gray-300 rounded-md px-4 h-10 text-sm w-35 focus:outline-none focus:ring-2 focus:ring-green-700 focus:border-green-700 transition-all placeholder:text-gray-400"
                >
            </div>

            <button 
                wire:click="exportToExcel"
                class="bg-green-700 text-white border whitespace-nowrap border-green-700 rounded-md px-5 h-10 text-sm font-medium hover:bg-green-800 transition-colors flex items-center gap-2 shadow-sm"
            >
                Exportar a Excel
            </button>
        </div>
    </div>

    @if($minimumStock !== null && $minimumStock !== '')
        <div class="mb-4">
            <span class="text-xs font-semibold uppercase bg-amber-100 text-amber-700 py-1 px-2.5 rounded-full tracking-wide">
                Mostrando productos con stock menor o igual a {{ (int) $minimumStock }}
            </span>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4 md:p-6">
        <div class="overflow-x-auto rounded-md border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm text-left">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs">Código Local</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs">Descripción del Producto</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs text-center">Marca</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs text-right">Stock</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs text-center">Estado</th>
                    </tr>
                </thead>
                
                <tbody class="divide-y divide-gray-100 bg-white text-sm text-gray-700">
                    @forelse ($products as $product)
                        <tr wire:key="row-{{ $product->id }}" class="bg-white even:bg-gray-50 hover:bg-gray-100 transition-colors">
                            <td class="px-4 py-3 font-semibold text-gray-900 uppercase whitespace-nowrap">
                                {{ $product->code }}
                            </td>
                            
                            <td class="px-4 py-3 max-w-75 truncate" title="{{ $product->description }}">
                                {{ $product->description ?: '-' }}
                            </td>
                            
                            <td class="px-4 py-3 text-center text-gray-600">
                                {{ $product->brand ?: '-' }}
                            </td>
                            
                            <td class="px-4 py-3 text-right font-semibold text-gray-900">
                                {{ $product->resolved_stock }}
                            </td>
                            
                            <td class="px-4 py-3 text-center">
                                @if($product->resolved_stock == 0)
                                    <span class="text-xs font-semibold uppercase bg-red-100 text-red-700 py-1 px-2.5 rounded-full tracking-wide">
                                        Agotado
                                    </span>
                                @elseif($product->resolved_stock <= 5)
                                    <span class="text-xs font-semibold uppercase bg-amber-100 text-amber-700 py-1 px-2.5 rounded-full tracking-wide">
                                        Bajo Stock
                                    </span>
                                @else
                                    <span class="text-xs font-semibold uppercase bg-green-100 text-green-700 py-1 px-2.5 rounded-full tracking-wide">
                                        Óptimo
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-gray-500">
                                No se encontraron productos. Asegúrate de completar la conciliación.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="px-2 md:px-0 pt-4">
            {{ $products->links() }}
        </div>
    </div>
</div>