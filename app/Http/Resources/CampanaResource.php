<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampanaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'tituloCampana' => $this->tituloCampana,
            'mensajeCampana' => $this->mensajeCampana,
            'nombre_plantilla' => $this->nombre_plantilla,
            'idComercio' => $this->idComercio,
            'imagenCampana' => $this->imagenCampana,
            'urlimagen' => $this->urlimagen, // Accesor
            'horasCampana' => $this->horasCampana,
            'minutosCampana' => $this->minutosCampana,
            'nombre_comercio' => $this->whenLoaded('comercio', function () {
                return $this->comercio->nombre;
            }),
        ];
    }

}
