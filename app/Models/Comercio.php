<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comercio extends Model
{
    use HasFactory, HasApiTokens;

    protected $table = 'comercios'; // Nombre de la tabla

    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'email',
        'password',
        'token',
        'horas',
        'minutos',
        'activo',
        'bloqueado',
        'cuenta',
        'campana'
    ];

    protected $hidden = [
        'password',
    ];

    public function campanas() {
        return $this->hasMany(Campana::class, 'idComercio');
    }

    public function resenas() {
        return $this->hasMany(Resena::class, 'comercioId');
    }

    protected static function boot() {
        parent::boot();

        static::deleting(function ($comercio) {
            $comercio->campanas()->delete();
            $comercio->resenas()->delete();
        });
    }

}
