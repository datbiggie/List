<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? 'Johbri C.A.' }}</title>
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-50 font-sans antialiased text-slate-800">
        <header class="sticky top-0 z-50 w-full bg-white border-b border-gray-200 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 md:px-8 h-16 flex items-center justify-between">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 transition-opacity hover:opacity-90">
                    <span class="font-semibold uppercase text-lg tracking-tight text-slate-800">Johbri C.A.</span>
                </a>

                <nav class="flex items-center gap-1 md:gap-2">
                    <a href="{{ route('dashboard') }}" class="px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('dashboard') ? 'text-slate-900 bg-gray-100' : 'text-gray-600 hover:text-slate-900 hover:bg-gray-100' }}">
                    Resumen
                </a>    
                    <a href="{{ route('reconciliation.board') }}" class="px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('reconciliation.board') ? 'text-slate-900 bg-gray-100' : 'text-gray-600 hover:text-slate-900 hover:bg-gray-100' }}">
                    Revisión
                </a>
                    <a href="{{ route('inventory.final') }}" class="px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('inventory.final') ? 'text-slate-900 bg-gray-100' : 'text-gray-600 hover:text-slate-900 hover:bg-gray-100' }}">
                    Inventario
                </a>
                    <a href="{{ route('upload') }}" class="px-4 py-2 rounded-md text-sm font-medium transition-colors bg-green-700 text-white hover:bg-green-800 shadow-sm">
                    Carga
                </a>
                </nav>
            </div>
        </header>

        {{ $slot }}
    </body>
</html>