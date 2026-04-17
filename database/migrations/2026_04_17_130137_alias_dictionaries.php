<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alias_dictionaries', function (Blueprint $table) {
            $table->id();
            
            // Los códigos son strings porque los Excels suelen tener letras (ej. PROD-123)
            $table->string('local_code')->index();
            $table->string('supplier_code')->index();
            
            $table->timestamps();

            // Evitamos registros duplicados a nivel de base de datos
            $table->unique(['local_code', 'supplier_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alias_dictionaries');
    }
};