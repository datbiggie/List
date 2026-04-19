<main class="p-2 md:p-8 font-sans selection:bg-green-100 selection:text-slate-900">
    <div class="max-w-7xl mx-auto bg-white rounded-lg shadow-xs border border-gray-100 p-4 md:p-6">
        
        <div class="flex justify-between items-center mb-3">
            <div>
                <h1 class="text-base font-bold text-gray-800">Inventario Actualizado</h1>
                <p class="text-gray-500 text-xs md:text-sm">Lista maestra de productos con el stock conciliado del proveedor.</p>
            </div>
        </div>

            <div x-data="{ showAdvancedMobile: false }" class="flex flex-col gap-4 mb-6 py-2 px-3 bg-slate-50 rounded-lg border border-gray-200 shadow-xs">
                <div class="flex flex-col md:flex-row gap-4">
                
                <div class="flex-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="Buscar por código o descripción..."
                        class="w-full pl-10 h-[38px] bg-white border border-gray-300 rounded-md px-4 text-sm focus:outline-none focus:ring-2 focus:ring-green-800 transition-shadow"
                    >
                </div>

                <div class="flex gap-2 md:hidden">
                    <div class="flex-1"></div> <button
                        type="button"
                        @click="showAdvancedMobile = !showAdvancedMobile"
                        class="flex items-center justify-center w-[46px] h-[38px] bg-white border border-gray-300 text-slate-700 rounded-md hover:bg-gray-100 transition-colors"
                        title="Filtros avanzados"
                    >
                        <svg class="w-5 h-5 transition-transform duration-200" :class="showAdvancedMobile ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>

                <div class="md:w-48" :class="showAdvancedMobile ? 'block' : 'hidden md:block'">
                    <input
                        wire:model.live.debounce.300ms="minimumStock"
                        type="number"
                        min="0"
                        step="1"
                        placeholder="Stock mínimo..."
                        class="w-full h-[38px] bg-white border border-gray-300 rounded-md px-4 text-sm focus:outline-none focus:ring-2 focus:ring-green-800 transition-shadow"
                    >
                </div>
            </div>

            <div class="flex-col md:flex-row justify-between items-start md:items-center gap-4" 
                 :class="showAdvancedMobile ? 'flex mt-2 pt-4 border-t border-gray-200 md:border-t-0 md:mt-0 md:pt-0' : 'hidden md:flex'">

                @if($products->total() > 0)
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500">
                              {{ $products->total() }} Productos|*
                        </span>
                    </div>
                @endif
                
                
                <div class="flex items-center">
                    @if($minimumStock !== null && $minimumStock !== '')
                        <span class="text-[11px] font-semibold uppercase bg-gray-100 text-amber-700 py-1 px-2.5 rounded border border-amber-200 tracking-wide">
                            Mostrando stock ≤ {{ (int) $minimumStock }}
                        </span>
                    @endif
                </div>

                <div class="flex gap-2 w-full md:w-auto mt-4 md:mt-0">
                    <button 
                        wire:click="exportToExcel"
                        class="w-full md:w-auto h-[38px] bg-green-800 text-white px-6 rounded-md text-sm font-medium hover:bg-green-700 transition-colors shadow-xs flex items-center justify-center gap-2"
                    >
                        Exportar a Excel
                    </button>
                    
                </div>
            </div>
        </div>

        <div class="md:hidden mt-4 space-y-2">
            @forelse ($products as $product)
                <details wire:key="mobile-row-{{ $product->id }}" class="group bg-white border border-gray-200 rounded-lg shadow-xs [&_summary::-webkit-details-marker]:hidden overflow-hidden">
                    <summary class="flex items-center justify-between p-4 cursor-pointer select-none outline-none hover:bg-gray-50 transition-colors">
                        <div class="flex-1 pr-4 overflow-hidden">
                            <p class="text-sm font-bold text-gray-900 truncate" title="{{ $product->description }}">
                                {{ $product->description ?: '-' }}
                            </p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[10px] font-bold text-green-700 bg-green-50 px-1.5 py-0.5 rounded border border-green-100 uppercase tracking-wider">
                                    {{ $product->code }}
                                </span>
                                <span class="text-xs text-gray-500">
                                    {{ $product->brand ?: 'Sin marca' }}
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-col items-end shrink-0">
                            <span class="text-base font-black text-gray-900">
                                {{ $product->resolved_stock }}
                            </span>
                            <svg class="w-4 h-4 text-gray-400 mt-1 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </summary>
                    <div class="px-4 pb-4 pt-3 border-t border-gray-100 bg-gray-50">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Estado</span>
                            @if($product->resolved_stock == 0)
                                <span class="text-[10px] font-semibold uppercase bg-red-100 text-red-700 py-1 px-2 rounded tracking-wide border border-red-200">Agotado</span>
                            @elseif($product->resolved_stock <= 5)
                                <span class="text-[10px] font-semibold uppercase bg-amber-100 text-amber-700 py-1 px-2 rounded tracking-wide border border-amber-200">Bajo Stock</span>
                            @else
                                <span class="text-[10px] font-semibold uppercase bg-green-100 text-green-700 py-1 px-2 rounded tracking-wide border border-green-200">Óptimo</span>
                            @endif
                        </div>
                    </div>
                </details>
            @empty
                <div class="bg-white p-8 text-center rounded-lg border border-gray-200 shadow-xs">
                    <p class="text-gray-500 font-medium">No se encontraron productos. Asegúrate de completar la conciliación.</p>
                </div>
            @endforelse
        </div>

        <div class="hidden md:block overflow-x-auto rounded-md border border-gray-200 mt-4">
            <table class="min-w-full divide-y divide-gray-200 text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700">Código Local</th>
                        <th class="px-4 py-3 font-semibold text-gray-700">Descripción del Producto</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 text-center">Marca</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 text-right">Stock</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($products as $product)
                        <tr wire:key="desktop-row-{{ $product->id }}" class="bg-white even:bg-gray-50 hover:bg-gray-100 transition-colors border-b border-gray-100">
                            <td 
                                x-data="clipboardItemFast('{{ $product->code }}')" 
                                @click="copy"
                                class="px-4 py-3 text-green-700 text-sm whitespace-nowrap font-bold cursor-pointer transition-colors relative"
                            >
                                <span :class="{ 'opacity-0': copied }" class="transition-opacity duration-100">
                                    {{ $product->code }}
                                </span>

                                <span 
                                    x-cloak
                                    x-show="copied" 
                                    class="absolute inset-0 flex items-center justify-center text-green-600 bg-green-100 font-medium"
                                >
                                    Copiado
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-700 max-w-xs truncate" title="{{ $product->description }}">
                                {{ $product->description ?: '-' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-center whitespace-nowrap">
                                {{ $product->brand ?: '-' }}
                            </td>
                            <td class="px-4 py-3 text-gray-800 text-right font-medium">
                                {{ $product->resolved_stock }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($product->resolved_stock == 0)
                                    <span class="text-[10px] font-semibold uppercase bg-red-100 text-red-700 py-1 px-2.5 rounded border border-red-200 tracking-wide">Agotado</span>
                                @elseif($product->resolved_stock <= 5)
                                    <span class="text-[10px] font-semibold uppercase bg-amber-100 text-amber-700 py-1 px-2.5 rounded border border-amber-200 tracking-wide">Bajo Stock</span>
                                @else
                                    <span class="text-[10px] font-semibold uppercase bg-green-100 text-green-700 py-1 px-2.5 rounded border border-green-200 tracking-wide">Óptimo</span>
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

        <div class="px-2 md:px-0 pt-4 mt-2 border-t border-gray-100">
            {{ $products->links() }}
        </div>
        
    </div>
</main>