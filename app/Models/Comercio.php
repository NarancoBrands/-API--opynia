<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

use App\Http\Controllers\WhatsAppController;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comercio extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $table = 'comercios'; // Nombre de la tabla
    protected $appends = ['google_connected'];
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
    public function getGoogleConnectedAttribute(): bool
    {
        return !is_null($this->google_refresh_token);
    }
}
