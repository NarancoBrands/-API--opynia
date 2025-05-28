<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resena extends Model
{
    use HasFactory;

    protected $appends = ['fecha'];

    protected $fillable = [
        'nombre',
        'telefono',
        'mensaje',
        'campanaId',
        'comercioId',
        'enviado',
    ];

    protected $casts = [
        'created_at'        => 'datetime:d-m-Y H:i:s',
        'updated_at'        => 'datetime:d-m-Y H:i:s',
    ];

    /**
     * Get the Campana that owns the Resenas
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function campana(): BelongsTo
    {
        return $this->belongsTo(Campana::class, 'campanaId');
    }

    /**
     * Get the Comercio that owns the Resenas
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'comercioId');
    }

    public function getFechaAttribute() {
        return $this->created_at->format('d/m/y');
    }
    

}
