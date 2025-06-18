<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use Illuminate\Http\Request;

class SolicitudController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nombre' => 'required|string|max:255',
            'asunto' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'descripcion' => 'required|string'
        ]);

        $solicitud = Solicitud::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud creada con éxito.',
            'data' => $solicitud
        ], 201);
    }

    public function destroy(Solicitud $solicitud)
    {
        $solicitud->delete();

        return response()->json([
            'success' => true,
            'message' => 'Solicitud eliminada correctamente.'
        ], 200);
    }

    public function index()
    {
        $solicitudes = Solicitud::latest()->paginate(10);
        return response()->json($solicitudes);
    }

}
