<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Resena;
use App\Models\Comercio;

class ResenasController extends Controller
{

    public function addResena(Request $request) {

        $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'required|integer',
            'mensaje' => 'required|string|max:255',
            // 'campanaId' => 'required|integer|exists:campanas,id',
            'comercioId' => 'required|integer|exists:comercios,id',
            // 'enviado' => 'nullable|boolean'
        ]);
                
        $data = $request->all(); // Obtener todos los datos del request

        $comercio = Comercio::find($request->comercioId);
        $data['campanaId'] = $comercio->campana;
        $data['enviado'] = false;

        $resena = Resena::create($data); 
    
        return $this->sendResponse($resena);

    }

    public function getResenasByIdComercio($id) {
        
        $resenas = Resena::where('comercioId', '=', $id)->get();

        return $this->sendResponse($resenas);

    }
    

}
