<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temp_supplier_inventories', function (Blueprint $table) {
            $table->id();
            
            // Indexamos el código para cruces JOIN rápidos con la tabla local
            $table->string('code')->index();
            $table->string('description')->nullable();
            $table->string('brand')->nullable();
            
            // Valor por defecto en caso de celdas vacías en el Excel
            $table->integer('quantity')->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temp_supplier_inventories');
    }
};