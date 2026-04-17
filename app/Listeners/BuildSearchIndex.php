<?php

namespace App\Listeners;

use App\Events\SupplierInventoryImported;
use App\Contracts\ProductMatcherInterface;

class BuildSearchIndex
{
    /**
     * Inyectamos nuestra interfaz por constructor. El contenedor de Laravel 
     * nos entregará el TntProductMatcher que registramos en el AppServiceProvider.
     */
    public function __construct(
        protected ProductMatcherInterface $matcher
    ) {}

    /**
     * Handle the event.
     */
    public function handle(SupplierInventoryImported $event): void
    {
        // Construimos el índice solo cuando el proveedor ha sido importado
        $this->matcher->buildIndex();
    }
}