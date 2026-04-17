<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Dashboard;
use App\Livewire\InventoryUpload;
use App\Livewire\ReconciliationBoard;
use App\Livewire\FinalInventoryTable;

// Vista principal (Métricas)
Route::get('/', Dashboard::class)->name('dashboard');

// Vista de carga de archivos Excel
Route::get('/upload', InventoryUpload::class)->name('upload');

// Vista de la pizarra de revisión (Human-in-the-loop)
Route::get('/reconciliation', ReconciliationBoard::class)->name('reconciliation.board');

// Vista de la tabla final de resultados
Route::get('/inventory', FinalInventoryTable::class)->name('inventory.final');