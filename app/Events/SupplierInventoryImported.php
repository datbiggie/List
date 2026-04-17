<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SupplierInventoryImported
{
    use Dispatchable;

    // Podríamos pasar el batchId o la ruta del archivo si fuera necesario, 
    // pero para este caso, solo el disparo del evento es suficiente.
    public function __construct()
    {
    }
}