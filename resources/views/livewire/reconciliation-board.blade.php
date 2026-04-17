<div class="max-w-7xl mx-auto px-4 md:px-8 pt-8 pb-24">
    <div class="mb-6 flex flex-col gap-3 md:flex-row md:justify-between md:items-end">
        <div>
            <h2 class="text-slate-800 font-bold text-2xl md:text-3xl">Revisión Manual</h2>
            <p class="text-gray-600 text-sm md:text-base">Confirma las coincidencias detectadas por similitud.</p>
        </div>
        <div class="text-gray-700 text-sm">
            Pendientes: <span class="font-mono bg-gray-100 border border-gray-200 px-2 py-1 rounded-md">{{ $products->total() }}</span>
        </div>
    </div>

    <div class="flex flex-col gap-6">
        @forelse ($products as $localProduct)
            @php 
                $suggestion = $suggestions[$localProduct->id] ?? null; 
            @endphp

            <div wire:key="product-{{ $localProduct->id }}" class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm transition-colors">
                
                @if($suggestion)
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-sans text-xl font-semibold text-slate-900">
                            Posible Coincidencia
                        </h3>
                        <span class="text-xs font-semibold tracking-wide uppercase bg-green-100 text-green-700 py-1 px-3 rounded-full">
                            {{ $suggestion['confidence'] }}% Similitud
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                            <span class="text-xs font-semibold tracking-wide uppercase text-gray-500 block mb-2">Tu Producto</span>
                            <p class="font-mono text-xs font-semibold tracking-wide uppercase text-slate-900 mb-1">
                                {{ $localProduct->code }}
                            </p>
                            <p class="font-sans text-sm text-gray-700 truncate" title="{{ $localProduct->description }}">
                                {{ $localProduct->description ?: 'Sin descripción' }}
                            </p>
                        </div>

                        <div class="p-4 rounded-lg bg-white border border-green-200 relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-1 h-full bg-green-600"></div>
                            <span class="text-xs font-semibold tracking-wide uppercase text-gray-500 block mb-2">Sugerencia Proveedor</span>
                            <p class="font-mono text-xs font-semibold tracking-wide uppercase text-slate-900 mb-1">
                                {{ $suggestion['code'] }}
                            </p>
                            <p class="font-sans text-sm text-gray-700 truncate">
                                {{ $suggestion['description'] ?: 'Sin descripción' }}
                            </p>
                            <div class="mt-3 flex justify-end">
                                <span class="text-xs font-semibold bg-green-100 text-green-700 py-0.5 px-2 rounded-md">
                                    Stock: {{ $suggestion['quantity'] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <button wire:click="discard({{ $localProduct->id }})" class="bg-white cursor-pointer text-slate-800 border border-gray-300 rounded-md px-4 h-10 text-sm font-medium hover:bg-gray-100 transition-colors">
                            Descartar
                        </button>
                        <button wire:click="approveMatch({{ $localProduct->id }}, '{{ $localProduct->code }}', '{{ $suggestion['code'] }}', {{ $suggestion['quantity'] }})" class="bg-green-700 cursor-pointer text-white rounded-md px-6 h-10 text-sm font-medium hover:bg-green-800 transition-colors shadow-sm">
                            Aprobar y Vincular
                        </button>
                    </div>
                @else
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-mono text-xs font-semibold tracking-wide uppercase text-slate-900 mb-1">
                                {{ $localProduct->code }}
                            </p>
                            <p class="font-sans text-sm text-gray-700">{{ $localProduct->description }}</p>
                            <span class="inline-block mt-2 text-xs font-semibold bg-red-100 text-red-700 py-0.5 px-2 rounded-md uppercase tracking-wide">
                                Sin coincidencias en el proveedor
                            </span>
                        </div>
                        <button wire:click="discard({{ $localProduct->id }})" class="bg-white text-slate-800 border border-gray-300 rounded-md px-4 h-10 text-sm font-medium hover:bg-gray-100 transition-colors">
                            Marcar como Agotado
                        </button>
                    </div>
                @endif
            </div>
        @empty
            <div class="text-center py-16 bg-white border border-gray-200 rounded-lg">
                <p class="text-slate-900 text-lg font-medium">¡Todo listo!</p>
                <p class="text-gray-600 text-sm mt-2">No hay productos pendientes por conciliar.</p>
            </div>
        @endforelse

        <div class="mt-6">
            {{ $products->links() }}
        </div>
    </div>
</div>