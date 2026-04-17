<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\InventoryParserInterface;
use App\Services\ExcelInventoryParser;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
{
    // El parser de Excel
    $this->app->bind(\App\Contracts\InventoryParserInterface::class, \App\Services\ExcelInventoryParser::class);
    
    // Nuestro motor de búsqueda
    $this->app->bind(\App\Contracts\ProductMatcherInterface::class, \App\Services\TntProductMatcher::class);
}

    public function boot(): void
    {
        //
    }
}
