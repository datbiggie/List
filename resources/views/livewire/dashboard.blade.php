<div class="w-full min-h-screen font-sans selection:bg-green-100 selection:text-slate-900">
    <main class="max-w-7xl mx-auto px-4 md:px-8 pt-8 pb-24">
        
        <div class="mb-8 flex flex-col gap-4 md:flex-row md:justify-between md:items-end">
            <div>
                <h1 class="text-slate-800 font-bold text-3xl md:text-4xl mb-1">
                    Resumen de Inventario
                </h1>
                <p class="text-gray-600 text-base md:text-lg">
                    Controla el estado actual de tu conciliación de stock.
                </p>
            </div>
            
            <div class="flex gap-3">
                @if($pendingReconciliation > 0)
                    <a href="{{ route('reconciliation.board') }}" class="inline-flex items-center justify-center bg-white text-slate-800 border border-gray-300 rounded-md px-4 h-10 text-sm font-medium hover:bg-gray-100 transition-colors">
                        Continuar Revisión
                    </a>
                @endif
                
                <a href="{{ route('upload') }}" class="inline-flex items-center justify-center bg-green-700 text-white rounded-md px-6 h-10 text-sm font-medium shadow-sm hover:bg-green-800 transition-colors">
                    Nueva Carga
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            
            <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 left-0 h-1 bg-gray-100 w-full">
                    <div class="h-full bg-green-600 transition-all duration-500" style="width: {{ $progressPercentage }}%"></div>
                </div>
                
                <h3 class="font-sans text-base font-medium text-gray-600 mb-4 mt-2">Estado de Conciliación</h3>
                <div class="flex items-baseline gap-2">
                    <span class="font-sans text-4xl font-bold text-slate-900">{{ $progressPercentage }}%</span>
                </div>
                <div class="mt-4 flex justify-between text-xs">
                    <span class="font-mono text-gray-500 uppercase tracking-wide">Completados: <span class="text-slate-900">{{ $resolvedCount }}</span></span>
                    <span class="font-mono text-red-600 uppercase tracking-wide">Pendientes: {{ $pendingReconciliation }}</span>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                <h3 class="font-sans text-base font-medium text-gray-600 mb-4">Volumen Procesado</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center border-b border-gray-100 pb-3">
                        <span class="text-sm text-gray-700">Mi Inventario</span>
                        <span class="font-mono text-sm font-medium text-slate-900 bg-gray-100 px-2 py-0.5 rounded-md">{{ number_format($totalLocal) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-700">Inventario Proveedor</span>
                        <span class="font-mono text-sm font-medium text-slate-900 bg-gray-100 px-2 py-0.5 rounded-md">{{ number_format($totalSupplier) }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="font-sans text-base font-medium text-gray-600">Base de Conocimiento</h3>
                    <span class="font-mono text-xs bg-green-100 text-green-700 py-0.5 px-2 rounded-md uppercase tracking-wide">Activo</span>
                </div>
                <div class="flex items-baseline gap-2 mb-2">
                    <span class="font-sans text-3xl font-bold text-slate-900">{{ number_format($learnedAliases) }}</span>
                </div>
                <p class="text-sm text-gray-600 leading-relaxed">
                    Alias aprendidos. Estos productos se cruzarán automáticamente al 100% en tu próxima carga.
                </p>
            </div>

        </div>

    </main>
</div>