<?php

namespace App\Http\Controllers;

use App\Models\Campana;
use Illuminate\Http\Request;
use App\Models\Resena;
use App\Models\Comercio;

class ResenasController extends Controller
{

    public function addResena(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'required|string',
            'mensaje' => 'required|string|max:255',
            'comercioId' => 'required|integer|exists:comercios,id',
        ]);

        // Obtener el comercio y la campaña asociada
        $comercio = Comercio::findOrFail($request->comercioId);
        $campanaId = $comercio->campana;

        if (!$campanaId || !Campana::where('id', $campanaId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'La campaña asociada al comercio no existe.'
            ], 422);
        }

        $data = $request->all();
        $data['campanaId'] = $campanaId;  // inyectamos el id de campaña asociado
        $data['enviado'] = false;

        $resena = Resena::create($data);

        return $this->sendResponse($resena);
    }

    public function getResenasByIdComercio($id) {
        
        $resenas = Resena::where('comercioId', '=', $id)->get();

        return $this->sendResponse($resenas);

    }
    public function destroy($id)
    {
        $resena = Resena::find($id);

        if (!$resena) {
            return response()->json([
                'success' => false,
                'message' => 'Reseña no encontrada.'
            ], 404);
        }

        $resena->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reseña eliminada correctamente.'
        ]);
    }

}
