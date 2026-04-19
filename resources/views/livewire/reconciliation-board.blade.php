<div class="max-w-7xl mx-auto px-4 md:px-8 pt-8 pb-24">
    {{-- ENCABEZADO --}}
    <div class="mb-8 flex flex-col gap-4 md:flex-row md:justify-between md:items-end">
        <div>
            <h2 class="text-slate-900 font-bold text-2xl md:text-3xl tracking-tight">Revisión Manual</h2>
            <p class="text-slate-500 text-sm md:text-base mt-1">Confirma o descarta las coincidencias detectadas por el sistema.</p>
        </div>
        <div class="flex items-center gap-2 text-sm font-medium text-slate-600 bg-white border border-gray-200 px-4 py-2 rounded-lg shadow-sm">
            <span>Pendientes por revisar:</span>
            <span class="font-mono font-bold text-green-600">{{ $products->total() }}</span>
        </div>
    </div>

    {{-- CONTENEDOR PRINCIPAL --}}
    <div class="flex flex-col gap-6">
        @forelse ($products as $localProduct)
            @php 
                $suggestion = $suggestions[$localProduct->id] ?? null; 
            @endphp

            <div wire:key="product-{{ $localProduct->id }}" x-data="{ manualSearchVisible: false }" class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden transition-all hover:shadow-md">
                
                @if($suggestion)
                    {{-- ESTADO: COINCIDENCIA ENCONTRADA --}}
                    <div class="bg-slate-50/50 border-b border-gray-100 px-6 py-4 flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                            </span>
                            <h3 class="font-semibold text-slate-800 text-sm uppercase tracking-wide">
                                Posible Coincidencia
                            </h3>
                        </div>
                        <span class="text-xs font-bold tracking-wide uppercase bg-green-100 text-green-700 py-1 px-3 rounded-full border border-green-200">
                            {{ $suggestion['confidence'] }}% Similitud
                        </span>
                    </div>

                    <div class="p-6">
                        <div class="relative grid grid-cols-1 md:grid-cols-2 gap-6 items-stretch">
                            
                            {{-- Producto Local --}}
                            <div class="p-5 rounded-xl bg-white border border-gray-200 shadow-sm flex flex-col">
                                <span class="text-[10px] font-bold tracking-wider uppercase text-gray-600 mb-3">Tu Producto</span>
                                <p class="font-mono text-base font-bold text-green-600 mb-2">
                                    {{ $localProduct->code }}
                                </p>
                                <p class="font-sans text-sm text-slate-700 leading-relaxed line-clamp-3" title="{{ $localProduct->description }}">
                                    {{ $localProduct->description ?: 'Sin descripción registrada' }}
                                </p>
                            </div>



                            {{-- Sugerencia --}}
                            <div class="p-5 rounded-xl bg-green-50/30 border border-green-200 shadow-sm flex flex-col relative overflow-hidden">
                                <div class="flex justify-between items-start mb-3">
                                    <span class="text-[10px] font-bold tracking-wider uppercase text-green-700">Catálogo Proveedor</span>
                                    <span class="text-[10px] font-bold bg-white border border-green-100 text-green-700 py-1 px-2 rounded">
                                        Stock: {{ $suggestion['quantity'] }}
                                    </span>
                                </div>
                                <p class="font-mono text-base font-bold text-slate-900 mb-2">
                                    {{ $suggestion['code'] }}
                                </p>
                                <p class="font-sans text-sm text-slate-700 leading-relaxed line-clamp-3" title="{{ $suggestion['description'] }}">
                                    {{ $suggestion['description'] ?: 'Sin descripción registrada' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Acciones --}}
                    <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row justify-end gap-3 border-t border-gray-100">
                        <button wire:click="discard({{ $localProduct->id }})" 
                                wire:loading.attr="disabled"
                                class="inline-flex justify-center items-center bg-white text-slate-700 border border-gray-300 rounded-lg px-5 h-10 text-sm font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                            Descartar Sugerencia
                        </button>
                        
                        <button wire:click="approveMatch({{ $localProduct->id }}, {{ (int) $suggestion['id'] }})" 
                                wire:loading.attr="disabled"
                                class="inline-flex justify-center items-center bg-green-600 text-white rounded-lg px-6 h-10 text-sm font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all shadow-sm disabled:opacity-50 disabled:cursor-not-allowed gap-2">
                            <svg wire:loading wire:target="approveMatch({{ $localProduct->id }}, {{ (int) $suggestion['id'] }})" class="animate-spin -ml-1 mr-1 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span wire:loading.remove wire:target="approveMatch({{ $localProduct->id }}, {{ (int) $suggestion['id'] }})">
                                Aprobar y Vincular
                            </span>
                            <span wire:loading wire:target="approveMatch({{ $localProduct->id }}, {{ (int) $suggestion['id'] }})">
                                Procesando...
                            </span>
                        </button>
                    </div>

                @else
                    {{-- ESTADO: SIN COINCIDENCIAS --}}
                    <div class="p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 mb-2">
                                <p class="font-mono text-sm font-bold text-green-600">
                                    {{ $localProduct->code }}
                                </p>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-red-50 text-red-700 border border-red-100">
                                    Sin coincidencias
                                </span>
                            </div>
                            <p class="font-sans text-sm text-slate-600 truncate" title="{{ $localProduct->description }}">
                                {{ $localProduct->description }}
                            </p>
                        </div>
                        
                        <div class="shrink-0 w-full md:w-auto">
                            <button wire:click="discard({{ $localProduct->id }})" 
                                    wire:loading.attr="disabled"
                                    class="w-full md:w-auto inline-flex justify-center items-center bg-white text-slate-700 border border-gray-300 rounded-lg px-5 h-10 text-sm font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg wire:loading wire:target="discard({{ $localProduct->id }})" class="animate-spin -ml-1 mr-2 h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Marcar como Agotado
                            </button>
                        </div>
                    </div>
                @endif

                @php
                    $manualOptions = $manualCandidates[$localProduct->id] ?? [];
                    $manualQuery = trim((string) ($manualQueries[$localProduct->id] ?? ''));
                @endphp

                <div class="px-6 py-3 border-t border-gray-100 bg-slate-50/40 flex items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">¿No coincide? Puedes vincular manualmente</p>
                    <button
                        type="button"
                        x-on:click="manualSearchVisible = !manualSearchVisible"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-gray-50 focus:outline-none focus:ring focus:ring-green-400"
                    >
                        <span x-text="manualSearchVisible ? 'Ocultar búsqueda' : 'Vincular manualmente'"></span>
                    </button>
                </div>

                {{-- Vinculación Manual Inteligente (Opcional) --}}
                <div x-show="manualSearchVisible" x-cloak class="px-6 py-4 border-t border-gray-100 bg-slate-50/50">
                        <div class="flex flex-col gap-3">
                            <div class="flex-1">
                                <label class="text-[11px] font-bold uppercase tracking-wide text-slate-600 mb-1 block">
                                    Buscar producto proveedor para vincular
                                </label>
                                <input
                                    type="text"
                                    wire:model.live.debounce.350ms="manualQueries.{{ $localProduct->id }}"
                                    wire:keydown.enter="searchSupplierCandidates({{ $localProduct->id }})"
                                    placeholder="Escribe para autocompletar: código, marca o descripción"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                >
                            </div>

                            @error('manualQueries.' . $localProduct->id)
                                <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror

                            @if(mb_strlen($manualQuery) >= 2)
                                @if(!empty($manualOptions))
                                    <div class="mt-1 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                                        <ul class="max-h-72 overflow-y-auto divide-y divide-gray-100">
                                            @foreach($manualOptions as $candidate)
                                                <li wire:key="manual-candidate-{{ $localProduct->id }}-{{ $candidate['id'] }}" class="px-3 py-2.5 hover:bg-slate-50 transition-colors">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <p class="font-mono text-sm font-bold text-slate-900 truncate">
                                                                {{ $candidate['code'] }}
                                                            </p>
                                                            <p class="text-xs text-slate-600 mt-0.5 line-clamp-2" title="{{ $candidate['description'] }}">
                                                                {{ $candidate['description'] ?: 'Sin descripción registrada' }}
                                                            </p>
                                                        </div>

                                                        <div class="shrink-0 flex items-center gap-2">
                                                            <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded border border-green-200 bg-green-50 text-green-700">
                                                                {{ $candidate['confidence'] }}%
                                                            </span>
                                                            <button
                                                                wire:click="approveMatch({{ $localProduct->id }}, {{ $candidate['id'] }})"
                                                                wire:loading.attr="disabled"
                                                                class="inline-flex items-center justify-center rounded-md bg-green-600 text-white px-3 py-1.5 text-xs font-semibold hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                                            >
                                                                Vincular
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <div class="mt-1 text-[11px] text-slate-500">
                                                        Marca: {{ $candidate['brand'] ?: '-' }} | Stock: <strong class="text-slate-700">{{ $candidate['quantity'] }}</strong>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @else
                                    <p class="text-xs text-slate-500 mt-1">Sin resultados para esa búsqueda.</p>
                                @endif
                            @endif
                        </div>
                    </div>
            </div>
        @empty
            {{-- ESTADO: VACÍO --}}
            <div class="text-center py-20 bg-white border border-gray-200 rounded-2xl shadow-sm">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-50 mb-4">
                    <svg class="h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </div>
                <h3 class="text-slate-900 text-lg font-bold">¡Revisión Completada!</h3>
                <p class="text-slate-500 text-sm mt-2 max-w-sm mx-auto">Todos los productos han sido conciliados exitosamente con el catálogo del proveedor.</p>
            </div>
        @endforelse

        {{-- PAGINACIÓN --}}
        @if($products->hasPages())
            <div class="mt-4 bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>