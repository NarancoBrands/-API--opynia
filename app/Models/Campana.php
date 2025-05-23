<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campana extends Model
{

    protected $appends = ['imagen_campana_url'];

    use HasFactory;

    protected $table = 'campanas';

    protected $fillable = [
        'tituloCampana',
        'mensajeCampana',
        'idComercio',
        'imagenCampana',
        'horasCampana',
        'minutosCampana'
    ];

    // Relación con el modelo Comercio (si es necesario)
    public function comercio()
    {
        return $this->belongsTo(Comercio::class, 'idComercio');
    }

    public function getImagenCampanaUrlAttribute()
    {
        return env('FILES_URL'). '/' .$this->attributes['imagenCampana'];
    }

}
