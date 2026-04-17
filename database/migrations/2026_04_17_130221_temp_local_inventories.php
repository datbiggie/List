<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temp_local_inventories', function (Blueprint $table) {
            $table->id();
            
            // Indexamos el código local para acelerar el cruce inicial
            $table->string('code')->index();
            $table->string('description')->nullable();
            $table->string('brand')->nullable();
            
            // Columnas de estado (State) para el flujo "Human-in-the-loop"
            $table->boolean('is_resolved')->default(false);
            $table->integer('resolved_stock')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temp_local_inventories');
    }
};