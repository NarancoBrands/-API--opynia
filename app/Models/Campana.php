<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
class Campana extends Model
{


    use HasFactory;

    protected $table = 'campanas';

    protected $fillable = [
        'tituloCampana',
        'mensajeCampana',
        'nombre_plantilla',
        'idComercio',
        'imagenCampana',
        'idImagenMeta',
        'horasCampana',
        'minutosCampana'
    ];

    protected $appends = ['urlimagen'];
    // Relación con el modelo Comercio (si es necesario)
    public function comercio()
    {
        return $this->belongsTo(Comercio::class, 'idComercio');
    }

    public function getUrlimagenAttribute()
    {

        if ($this->attributes['imagenCampana']) {

            return url('storage/imagenes/' . $this->attributes['imagenCampana']);
        }
        return null;
    }

}
