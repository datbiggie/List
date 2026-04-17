<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TempLocalInventory extends Model
{
    // Al ser una tabla temporal interna de la app, podemos desprotegerla 
    // para facilitar la inserción masiva desde el Excel.
    protected $guarded = [];
}