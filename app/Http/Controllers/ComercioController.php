<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comercio;
use App\Models\Campana;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;


class ComercioController extends Controller
{
    /**
     * Almacena un nuevo comercio en la base de datos.
     */
    public function store(Request $request)
    {


        // return json_encode($request->all());


        // Validar los datos de entrada
        $validatedData = $request->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
            'telefono' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string',
            'password_confirmation' => 'required|same:password',
            'horas' => 'required|integer',
            'minutos' => 'required|integer',
            'activo' => 'nullable|boolean',
            'bloqueado' => 'nullable|boolean',
            'cuenta' => 'required|string|max:255',
            'tituloCampana' => 'required|string|max:255',
            'mensajeCampana' => 'required|string|max:255',
            // 'imagenCampana' => 'required|string|max:255',
            'imagenCampana' => 'required',
            'horasCampana' => 'required|integer',
            'minutosCampana' => 'required|integer',
        ]);

        // Iniciar la transacción de la base de datos
        DB::beginTransaction();

        try {
            // Crear el comercio en la base de datos
            $comercio = Comercio::create([
                'nombre' => $validatedData['nombre'],
                'direccion' => $validatedData['direccion'],
                'telefono' => $validatedData['telefono'],
                'email' => $validatedData['email'] ?? null,
                'password' => Hash::make($validatedData['password']),
                'token' => Hash::make($validatedData['email']),
                'horas' => $validatedData['horas'],
                'minutos' => $validatedData['minutos'],
                'activo' => $validatedData['activo'] ?? true,
                'bloqueado' => $validatedData['bloqueado'] ?? false,
                'cuenta' => $validatedData['cuenta'],
            ]);

            // Guardar la imagen si se proporciona
            $imagenPath = null;
            if ($request->hasFile('imagenCampana')) {
                $imagenPath = $request->file('imagenCampana')->store('campanas', 'public');
            }

            $data = $request->all(); // Obtener todos los datos del request
    
            if ($request->hasFile('imagenCampana')) {
                $imagen = $this->addPhoto($request)->getData();
        
                if(!$imagen->success) {
                    return $this->sendError($imagen->message, [], 400);
                }
                $data['imagenCampana'] = $imagen->data->filename;
            }

            // Crear la campaña asociada al comercio
            $campana = Campana::create([
                'tituloCampana' => $validatedData['tituloCampana'],
                'mensajeCampana' => $validatedData['mensajeCampana'],
                'idComercio' => $comercio->id,
                'imagenCampana' => $data['imagenCampana'],
                'horasCampana' => $validatedData['horasCampana'],
                'minutosCampana' => $validatedData['minutosCampana'],
            ]);

            $comercio->update([
                'campana' => $campana->id
            ]);

            // Confirmar la transacción
            DB::commit();

            return response()->json([
                'message' => 'Comercio y campaña creados con éxito',
                'comercio' => $comercio,
                'campana' => $campana
            ], 201);

        } catch (\Exception $e) {
            // Si ocurre un error, hacer rollback de la transacción
            DB::rollBack();

            return response()->json([
                'message' => 'Error al crear el comercio y la campaña',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener la lista de comercios.
     */
    public function index()
    {
        $comercios = Comercio::with('campanas')->with('resenas')->get();
        // return response()->json($comercios);
        return $this->sendResponse($comercios);
    }

    /**
     * Obtener un comercio específico por su ID.
     */
    public function show($id)
    {
        $comercio = Comercio::with('campanas')->with('resenas')->find($id);

        if (!$comercio) {
            return response()->json(['message' => 'Comercio no encontrado'], 404);
        }

        // return response()->json($comercio);
        return $this->sendResponse($comercio);
    }

    /**
     * Actualizar un comercio existente.
     */
    public function update(Request $request, $id)
    {
        $comercio = Comercio::find($id);

        if (!$comercio) {
            return response()->json(['message' => 'Comercio no encontrado'], 404);
        }

        // Validar los datos de entrada
        $validatedData = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'direccion' => 'sometimes|string|max:255',
            'telefono' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'horas' => 'sometimes|integer',
            'minutos' => 'sometimes|integer',
            'activo' => 'nullable|boolean',
            'bloqueado' => 'nullable|boolean',
            'cuenta' => 'sometimes|string|max:255',
            'campana' => 'nullable|integer'
        ]);

        // Actualizar los datos
        $comercio->update($validatedData);

        return response()->json([
            'message' => 'Comercio actualizado con éxito',
            'comercio' => $comercio
        ]);
    }

    /**
     * Eliminar un comercio.
     */
    public function destroy($id)
    {
        $comercio = Comercio::find($id);

        if (!$comercio) {
            return response()->json(['message' => 'Comercio no encontrado'], 404);
        }

        $comercio->delete();

        return response()->json(['message' => 'Comercio eliminado con éxito']);
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
