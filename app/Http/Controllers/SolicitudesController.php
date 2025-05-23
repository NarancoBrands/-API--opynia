<?php

namespace App\Http\Controllers;

use App\Models\Campana;
use Illuminate\Http\Request;

class SolicitudesController extends Controller
{
    // Obtener todas las campañas
    public function index()
    {
        $campanas = Campana::all();  // O puedes usar paginación: Campana::paginate(10);
        return response()->json($campanas);
    }

    // Crear una nueva campaña
    public function store(Request $request)
    {
        $request->validate([
            'titulo'=> 'required|string|max:255',
            'mensaje' => 'required|string|max:255',
            'idComercio' => 'required|integer|exists:comercios,id', // Si hay una relación con Comercio
            'imagen' => 'nullable|string',
            'horas' => 'required|integer',
            'minutos' => 'required|integer'
        ]);

        $campana = Campana::create($request->all());

        return response()->json($campana, 201);
    }

    // Obtener una campaña por su ID
    public function show($id)
    {
        $campana = Campana::find($id);

        if (!$campana) {
            return response()->json(['message' => 'Campaña no encontrada'], 404);
        }

        return response()->json($campana);
    }

    // Actualizar una campaña
    public function update(Request $request, $id)
    {
        $campana = Campana::find($id);

        if (!$campana) {
            return response()->json(['message' => 'Campaña no encontrada'], 404);
        }

        $request->validate([
            'titulo'=> 'required|string|max:255',
            'mensaje' => 'required|string|max:255',
            'idComercio' => 'required|integer|exists:comercios,id',
            'imagen' => 'nullable|string',
            'horas' => 'required|integer',
            'minutos' => 'required|integer'
        ]);

        $campana->update($request->all());

        return response()->json($campana);
    }

    // Eliminar una campaña
    public function destroy($id)
    {
        $campana = Campana::find($id);

        if (!$campana) {
            return response()->json(['message' => 'Campaña no encontrada'], 404);
        }

        $campana->delete();

        return response()->json(['message' => 'Campaña eliminada exitosamente']);
    }
}
