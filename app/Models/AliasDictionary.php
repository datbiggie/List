<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AliasDictionary extends Model
{
    // Solo permitimos llenar estos dos campos para mantener la seguridad
    protected $fillable = [
        'local_code',
        'supplier_code'
    ];
}