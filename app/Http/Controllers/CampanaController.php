<?php

namespace App\Http\Controllers;

use App\Models\Campana;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;


class CampanaController extends Controller
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
            'tituloCampana'=> 'required|string|max:255',
            'mensajeCampana' => 'required|string|max:255',
            'idComercio' => 'required|integer|exists:comercios,id',
            'imagenCampana' => 'nullable',
            'horasCampana' => 'required|integer',
            'minutosCampana' => 'required|integer'
        ]);
        
        $data = $request->all(); // Obtener todos los datos del request
    
        if ($request->hasFile('imagenCampana')) {
            $imagen = $this->addPhoto($request)->getData();
    
            if(!$imagen->success) {
                return $this->sendError($imagen->message, [], 400);
            }
            $data['imagenCampana'] = $imagen->data->filename;
        }
        
        $campana = Campana::create($data); // Usar el array modificado
    
        return $this->sendResponse($campana);
    }

    // Obtener una campaña por su ID dsa

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
            'tituloCampana'=> 'required|string|max:255',
            'mensajeCampana' => 'required|string|max:255',
            // 'idComercio' => 'required|integer|exists:comercios,id',
            'imagenCampana' => 'nullable',
            'horasCampana' => 'required|integer',
            'minutosCampana' => 'required|integer'
        ]);

        $data = $request->all();


        if ($request->hasFile('imagenCampana')) {
            $imagen = $this->addPhoto($request)->getData();
    
            if(!$imagen->success) {
                return $this->sendError($imagen->message, [], 400);
            }
            $data['imagenCampana'] = $imagen->data->filename;
        } else {
            $data['imagenCampana'] = $request->imagenCampana;
        }

        $campana->update($data);

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



    private function addPhoto(Request $request, $name = 'imagenCampana') {

        $validator = Validator::make($request->all(), [
            $name => 'file|mimes:jpeg,png,jpg,gif,webp|max:20480',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first(), [], 500);
        }

        if (!$request->hasFile($name)) {
            return $this->sendError('NO FILE', [], 500);
        }

        $file = $request->file($name);
        $filename = bin2hex(openssl_random_pseudo_bytes(8));
        $extension = $file->extension();
        $fileStore = $filename . '_' . time() . '.' . $extension;
        $path = $request->file($name)->move('imagenes', $fileStore);
        return $this->sendResponse(['filename' => $fileStore, 'path' => $path]);
    }
}
