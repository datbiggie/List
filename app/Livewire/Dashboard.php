<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\TempLocalInventory;
use App\Models\TempSupplierInventory;
use App\Models\AliasDictionary;

class Dashboard extends Component
{
    public function render()
    {
        // Métricas rápidas para el usuario
        $totalLocal = TempLocalInventory::count();
        $totalSupplier = TempSupplierInventory::count();
        
        $pendingReconciliation = TempLocalInventory::where('is_resolved', false)->count();
        $resolvedCount = TempLocalInventory::where('is_resolved', true)->count();
        
        // Progreso general (evitando división por cero)
        $progressPercentage = $totalLocal > 0 
            ? round(($resolvedCount / $totalLocal) * 100) 
            : 0;

        $learnedAliases = AliasDictionary::count();

        return view('livewire.dashboard', compact(
            'totalLocal',
            'totalSupplier',
            'pendingReconciliation',
            'resolvedCount',
            'progressPercentage',
            'learnedAliases'
        ));
    }
}