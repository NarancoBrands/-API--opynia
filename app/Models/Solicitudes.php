<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Solicitudes extends Model
{
    use HasFactory;

    protected $table = 'comercios'; // Nombre de la tabla

    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'correo',
        'cif',
        'Ciudad',
        'CodigoPostal',
        'TipoNegocio',
        'cuenta'
    ];
}
