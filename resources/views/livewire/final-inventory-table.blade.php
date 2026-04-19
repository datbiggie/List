<main class="p-2 md:p-8 font-sans selection:bg-green-100 selection:text-slate-900">
    <div class="max-w-7xl mx-auto bg-white rounded-lg shadow-xs border border-gray-100 p-4 md:p-6">
        
        <div class="flex justify-between items-center mb-3">
            <div>
                <h1 class="text-base font-bold text-gray-800">Inventario Actualizado</h1>
                <p class="text-gray-500 text-xs md:text-sm">Lista maestra de productos con el stock conciliado del proveedor.</p>
            </div>
        </div>

            <div x-data="{ filtersModalOpen: false, filtersTab: 'supplier' }" class="mb-6">
                @php
                    $hasMinimumStockFilter = $minimumStock !== null && $minimumStock !== '';
                    $hasSupplierBrandFilter = !empty($selectedBrands);
                    $hasLocalBrandFilter = !empty($selectedLocalBrands);
                    $activeFilterCount = ($hasMinimumStockFilter ? 1 : 0)
                        + ($hasSupplierBrandFilter ? 1 : 0)
                        + ($hasLocalBrandFilter ? 1 : 0);
                @endphp

                <div class="py-2 px-3 bg-slate-50 rounded-lg border border-gray-200 shadow-xs">
                    <div class="flex flex-col md:flex-row gap-3">
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
                                class="w-full pl-10 h-9.5 bg-white border border-gray-300 rounded-md px-4 text-sm focus:outline-none focus:ring-2 focus:ring-green-800 transition-shadow"
                            >
                        </div>

                        <div class="flex gap-2 w-full md:w-auto">
                            <button
                                type="button"
                                @click="filtersModalOpen = true"
                                class="w-full md:w-auto h-9.5 inline-flex items-center justify-center gap-2 bg-white border border-gray-300 text-slate-700 px-4 rounded-md text-sm font-medium hover:bg-gray-100 transition-colors"
                            >
                                <span>Filtros</span>
                                @if($activeFilterCount > 0)
                                    <span class="inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-green-700 text-white text-[11px] font-bold">
                                        {{ $activeFilterCount }}
                                    </span>
                                @endif
                            </button>

                            <button
                                wire:click="exportToExcel"
                                class="w-full md:w-auto h-9.5 bg-green-800 text-white px-6 rounded-md text-sm font-medium hover:bg-green-700 transition-colors shadow-xs flex items-center justify-center gap-2"
                            >
                                Exportar a Excel
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    x-show="filtersModalOpen"
                    x-transition.opacity
                    x-cloak
                    @click="filtersModalOpen = false"
                    class="fixed inset-0 z-40 bg-slate-900/45"
                ></div>

                <div
                    x-show="filtersModalOpen"
                    x-transition
                    x-cloak
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                >
                    <div
                        @click.stop
                        class="w-full max-w-2xl bg-white rounded-xl border border-gray-200 shadow-xl overflow-hidden"
                    >
                        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">Filtros de Inventario</h2>
                            <button
                                type="button"
                                @click="filtersModalOpen = false"
                                class="inline-flex items-center justify-center h-8 w-8 rounded-md text-slate-500 hover:bg-gray-100 hover:text-slate-700"
                                aria-label="Cerrar filtros"
                            >
                                X
                            </button>
                        </div>

                        <div class="p-5 space-y-5">
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wide text-slate-600 mb-1.5 block">Stock mínimo</label>
                                <input
                                    wire:model.live.debounce.300ms="minimumStock"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="Ejemplo: 5"
                                    class="w-full h-10 bg-white border border-gray-300 rounded-md px-3 text-sm focus:outline-none focus:ring-2 focus:ring-green-800 transition-shadow"
                                >
                            </div>

                            <div>
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700 select-none">
                                    <input
                                        type="checkbox"
                                        wire:model.live="includePendingAsOutOfStock"
                                        class="h-4 w-4 rounded border-gray-300 text-green-700 focus:ring-green-600"
                                    >
                                    Incluir pendientes como posible agotado
                                </label>
                            </div>

                            <div class="rounded-md border border-gray-200 bg-slate-50 px-3 py-3">
                                <div class="inline-flex rounded-md border border-gray-300 overflow-hidden">
                                    <button
                                        type="button"
                                        @click="filtersTab = 'supplier'"
                                        class="px-3 h-8 text-xs font-semibold transition-colors"
                                        :class="filtersTab === 'supplier' ? 'bg-green-700 text-white' : 'bg-white text-slate-700 hover:bg-gray-100'"
                                    >
                                        Marcas proveedor
                                    </button>
                                    <button
                                        type="button"
                                        @click="filtersTab = 'local'"
                                        class="px-3 h-8 text-xs font-semibold transition-colors"
                                        :class="filtersTab === 'local' ? 'bg-green-700 text-white' : 'bg-white text-slate-700 hover:bg-gray-100'"
                                    >
                                        Marcas local
                                    </button>
                                </div>

                                <div x-show="filtersTab === 'supplier'" x-cloak class="mt-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Ocultar marcas del proveedor</p>
                                        @if(!empty($selectedBrands))
                                            <button
                                                type="button"
                                                wire:click="$set('selectedBrands', [])"
                                                class="text-[11px] font-semibold text-green-700 hover:text-green-800"
                                            >
                                                Limpiar
                                            </button>
                                        @endif
                                    </div>

                                    @if(!empty($availableBrands))
                                        <div class="mt-2 max-h-52 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-1.5">
                                            @foreach($availableBrands as $brand)
                                                <label wire:key="brand-filter-supplier-{{ md5($brand) }}" class="inline-flex items-center gap-2 text-xs text-slate-700">
                                                    <input
                                                        type="checkbox"
                                                        wire:model.live="selectedBrands"
                                                        value="{{ $brand }}"
                                                        class="h-3.5 w-3.5 rounded border-gray-300 text-green-700 focus:ring-green-600"
                                                    >
                                                    <span class="truncate" title="{{ $brand }}">{{ $brand }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="mt-2 text-xs text-slate-500">No hay marcas de proveedor disponibles.</p>
                                    @endif
                                </div>

                                <div x-show="filtersTab === 'local'" x-cloak class="mt-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Ocultar marcas local</p>
                                        @if(!empty($selectedLocalBrands))
                                            <button
                                                type="button"
                                                wire:click="$set('selectedLocalBrands', [])"
                                                class="text-[11px] font-semibold text-green-700 hover:text-green-800"
                                            >
                                                Limpiar
                                            </button>
                                        @endif
                                    </div>

                                    @if(!empty($availableLocalBrands))
                                        <div class="mt-2 max-h-52 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-1.5">
                                            @foreach($availableLocalBrands as $brand)
                                                <label wire:key="brand-filter-local-{{ md5($brand) }}" class="inline-flex items-center gap-2 text-xs text-slate-700">
                                                    <input
                                                        type="checkbox"
                                                        wire:model.live="selectedLocalBrands"
                                                        value="{{ $brand }}"
                                                        class="h-3.5 w-3.5 rounded border-gray-300 text-green-700 focus:ring-green-600"
                                                    >
                                                    <span class="truncate" title="{{ $brand }}">{{ $brand }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="mt-2 text-xs text-slate-500">No hay marcas locales disponibles.</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="px-5 py-3 border-t border-gray-200 flex items-center justify-end">
                            <button
                                type="button"
                                @click="filtersModalOpen = false"
                                class="h-9.5 px-4 rounded-md bg-green-700 text-white text-sm font-medium hover:bg-green-800 transition-colors"
                            >
                                Aplicar
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mt-3">

                @if($products->total() > 0)
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500">
                              {{ $products->total() }} Productos
                        </span>
                    </div>
                @endif
                
                
                <div class="flex items-center">
                    @if($minimumStock !== null && $minimumStock !== '')
                        <span class="text-[11px] font-semibold uppercase bg-gray-100 text-amber-700 py-1 px-2.5 rounded border border-amber-200 tracking-wide">
                            Mostrando stock ≤ {{ (int) $minimumStock }}
                        </span>
                    @endif

                    @if(!empty($selectedBrands))
                        <span class="ml-2 text-[11px] font-semibold uppercase bg-green-50 text-green-700 py-1 px-2.5 rounded border border-green-200 tracking-wide">
                            {{ count($selectedBrands) }} marcas proveedor ocultas
                        </span>
                    @endif

                    @if(!empty($selectedLocalBrands))
                        <span class="ml-2 text-[11px] font-semibold uppercase bg-slate-50 text-slate-700 py-1 px-2.5 rounded border border-slate-200 tracking-wide">
                            {{ count($selectedLocalBrands) }} marcas local ocultas
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="md:hidden mt-4 space-y-2">
            @forelse ($products as $product)
                @php
                    $displayStock = $product->is_resolved ? (int) ($product->resolved_stock ?? 0) : 0;
                @endphp
                <details wire:key="mobile-row-{{ $product->id }}" class="group bg-white border border-gray-200 rounded-lg shadow-xs [&_summary::-webkit-details-marker]:hidden overflow-hidden">
                    <summary class="flex items-center justify-between p-4 cursor-pointer select-none outline-none hover:bg-gray-50 transition-colors">
                        <div class="flex-1 pr-4 overflow-hidden">
                            <p class="text-sm font-bold text-gray-900 truncate" title="{{ $product->description }}">
                                {{ $product->description ?: '-' }}
                            </p>
                            <div class="flex items-center gap-2 mt-1">
                                <span
                                    x-data="clipboardItemFast('{{ $product->code }}')"
                                    @click="copy"
                                    class="relative text-[10px] font-bold text-green-700 bg-green-50 px-1.5 py-0.5 rounded border border-green-100 uppercase tracking-wider cursor-pointer"
                                >
                                    <span :class="{ 'opacity-0': copied }" class="transition-opacity duration-100">
                                        {{ $product->code }}
                                    </span>
                                    <span
                                        x-cloak
                                        x-show="copied"
                                        class="absolute inset-0 flex items-center justify-center text-green-700 bg-green-100"
                                    >
                                        Copiado
                                    </span>
                                </span>
                                <span class="text-xs text-gray-500">
                                    Prov: {{ $product->supplier_brand ?: 'Sin marca' }}
                                </span> 
                                <span class="text-[11px] text-gray-400">
                                      Local: {{ $product->brand ?: 'Sin marca' }}
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-col items-end shrink-0">
                            <span class="text-base font-black text-gray-900">
                                {{ $displayStock }}
                            </span>
                            <svg class="w-4 h-4 text-gray-400 mt-1 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </summary>
                    <div class="px-4 pb-4 pt-3 border-t border-gray-100 bg-gray-50">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Estado</span>
                            @if(!$product->is_resolved)
                                <span class="text-[10px] font-semibold uppercase bg-slate-100 text-slate-700 py-1 px-2 rounded tracking-wide border border-slate-200">Posible agotado</span>
                            @elseif($displayStock == 0)
                                <span class="text-[10px] font-semibold uppercase bg-red-100 text-red-700 py-1 px-2 rounded tracking-wide border border-red-200">Agotado</span>
                            @elseif($displayStock <= 5)
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
                        <th class="px-4 py-3 font-semibold text-gray-700 text-center">Marcas</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 text-right">Stock</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($products as $product)
                        @php
                            $displayStock = $product->is_resolved ? (int) ($product->resolved_stock ?? 0) : 0;
                        @endphp
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
                            <td class="px-4 py-3 text-center">
                                <div class="text-gray-700 text-sm font-semibold">
                                    {{ $product->supplier_brand ?: 'Sin marca proveedor' }}
                                </div>
                                <div class="text-[11px] text-green-800">
                                     {{ $product->brand ?: 'Sin marca local' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-800 text-right font-medium">
                                {{ $displayStock }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if(!$product->is_resolved)
                                    <span class="text-[10px] font-semibold uppercase bg-slate-100 text-slate-700 py-1 px-2.5 rounded border border-slate-200 tracking-wide">Posible agotado</span>
                                @elseif($displayStock == 0)
                                    <span class="text-[10px] font-semibold uppercase bg-red-100 text-red-700 py-1 px-2.5 rounded border border-red-200 tracking-wide">Agotado</span>
                                @elseif($displayStock <= 5)
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