<?php

namespace App\Http\Controllers;

use App\Models\Campana;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;


class CampanaController extends Controller
{
    private $graphApiBaseUrl;
    private $apiVersion;
    private $phoneNumberId;
    private $accessToken;

    public function __construct()
    {
        $this->graphApiBaseUrl = config('services.whatsapp.graph_api_base_url', 'https://graph.facebook.com');
        $this->apiVersion = config('services.whatsapp.api_version', 'v19.0'); // Use a current version
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->accessToken = config('services.whatsapp.access_token');
    }
    // Obtener todas las campañas
    public function index()
    {
        $campanas = Campana::all();  // O puedes usar paginación: Campana::paginate(10);
        return response()->json($campanas);
    }

    // Crear una nueva campaña
    public function store(Request $request)
    {
        // Validación de los datos de entrada
        $validatedData = $request->validate([
            'tituloCampana'=> 'required|string|max:255',
            'mensajeCampana' => 'required|string|max:255',
            'idComercio' => 'required|integer|exists:comercios,id',
            'imagenCampana' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:20480',
            'horasCampana' => 'required|integer',
            'minutosCampana' => 'required|integer'
        ]);

        $data = $validatedData;
        $data['idImagenMeta'] = null;

        if ($request->hasFile('imagenCampana')) {
            $fileToUpload = $request->file('imagenCampana');

            // Subida local
            $localImageUploadResponse = $this->addPhoto($request, 'imagenCampana')->getData();
            if (!$localImageUploadResponse->success) {
                return response()->json([
                    'message' => $localImageUploadResponse->message,
                    'success' => false
                ], 400);
            }

            $data['imagenCampana'] = $localImageUploadResponse->data->filename;

            // Subida a WhatsApp
            $whatsappMediaUploadResponse = $this->subirMediaAWsp($fileToUpload);
            if (isset($whatsappMediaUploadResponse['id'])) {
                $data['idImagenMeta'] = $whatsappMediaUploadResponse['id'];
            } else {
                Log::error('Fallo al subir medio a WhatsApp o obtener ID durante la creación.', $whatsappMediaUploadResponse);
            }
        } else {
            $data['imagenCampana'] = null;
        }

        // Creamos la campaña SIN nombre_plantilla todavía
        $campana = Campana::create($data);

        // Generamos el nombre de plantilla: plantilla_nombre_idcampana
        $tituloNormalizado = strtolower($validatedData['tituloCampana']);
        $tituloNormalizado = preg_replace('/[^a-z0-9_]/', '_', $tituloNormalizado);
        $tituloNormalizado = substr($tituloNormalizado, 0, 30);
        $nombrePlantilla = "plantilla_{$tituloNormalizado}_{$campana->id}";

        // Actualizamos la campaña con el nombre de plantilla generado
        $campana->nombre_plantilla = $nombrePlantilla;
        $campana->save();

        $campana->load('comercio');

        return response()->json(['data' => $campana, 'success' => true]);
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

        // Validación para la actualización (usando 'sometimes' para campos opcionales)
        $validatedData = $request->validate([
            'tituloCampana'=> 'sometimes|required|string|max:255',
            'mensajeCampana' => 'sometimes|required|string|max:255',
            'idComercio' => 'sometimes|required|integer|exists:comercios,id', // Generalmente no se cambia o se maneja con cuidado
         //   'imagenCampana' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:20480', // Para un nuevo archivo de imagen
            'horasCampana' => 'sometimes|required|integer',
            'minutosCampana' => 'sometimes|required|integer'
        ]);

        // Prepara los datos, excluyendo el archivo inicialmente
        // Usa collect para facilitar la exclusión del campo de archivo
        $data = collect($validatedData)->except('imagenCampana')->toArray();


        if ($request->hasFile('imagenCampana')) {
            $fileToUpload = $request->file('imagenCampana');

            // Opcional: Borrar la imagen antigua del almacenamiento si existe
            // if ($campana->imagenCampana) {
            //     Storage::disk('public')->delete('imagenes/' . $campana->imagenCampana);
            // }

            $localImageUploadResponse = $this->addPhoto($request, 'imagenCampana')->getData();
            if (!$localImageUploadResponse->success) {
                return response()->json(['message' => $localImageUploadResponse->message, 'success' => false], 400);
            }
            $data['imagenCampana'] = $localImageUploadResponse->data->filename; // Nuevo nombre de archivo local

            $whatsappMediaUploadResponse = $this->subirMediaAWsp($fileToUpload);
            if (isset($whatsappMediaUploadResponse['id'])) {
                $data['idImagenMeta'] = $whatsappMediaUploadResponse['id'];
            } else {
                Log::error('Fallo al subir medio a WhatsApp o obtener ID durante la actualización.', $whatsappMediaUploadResponse);
                // Se podría asignar null o mantener el idImagenMeta antiguo si la nueva subida falla
                $data['idImagenMeta'] = null; // O $campana->idImagenMeta;
            }
        }
        // Si no se sube un nuevo archivo ('imagenCampana' no está en $request o no es $request->hasFile(...)),
        // los campos $campana->imagenCampana y $campana->idImagenMeta no se verán afectados por este array $data.

        $campana->update($data); // Actualiza la campaña
        $campana->load('comercio'); // Carga la relación para la respuesta

        return response()->json(['data' => $campana, 'success' => true]);
    }

    // Eliminar una campaña
    public function destroy($id)
    {
        $campana = Campana::find($id);

        if (!$campana) {
            return response()->json(['message' => 'Campaña no encontrada'], 404);
        }

        // Opcional: Borrar la imagen del almacenamiento
         if ($campana->imagenCampana) {
            Storage::disk('public')->delete('imagenes/' . $campana->imagenCampana);
         }

        $campana->delete();

        return response()->json(['message' => 'Campaña eliminada exitosamente']);
    }
    public function updateTemplateName(Request $request, Campana $campana)
    {
        $validator = Validator::make($request->all(), [
            'nombre_plantilla' => 'required|string|max:512',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $campana->nombre_plantilla = $request->input('nombre_plantilla');
        $campana->save();

        return response()->json([
            'message' => 'Nombre de plantilla actualizado con éxito.',
            'data' => $campana
        ]);
    }


    // Método para guardar la imagen localmente
    private function addPhoto(Request $request, $name = 'imagenCampana') {
        // Validación específica para este método
        $validator = Validator::make($request->all(), [
            $name => 'required|file|mimes:jpeg,png,jpg,gif,webp|max:20480', // Se hace requerido aquí
        ]);

        if ($validator->fails()) {
            // Usa una estructura de respuesta de error consistente
            return response()->json(['message' => $validator->messages()->first(), 'success' => false, 'data' => null], 422);
        }

        if (!$request->hasFile($name)) {
            return response()->json(['message' => 'NO FILE', 'success' => false, 'data' => null], 400);
        }

        $file = $request->file($name);
        $filename = bin2hex(openssl_random_pseudo_bytes(8)); // Nombre aleatorio
        $extension = $file->extension();
        $fileStore = $filename . '_' . time() . '.' . $extension; // Nombre único con timestamp
        // Guarda en 'storage/app/public/imagenes'. Necesitas 'php artisan storage:link'
        $path = $file->storeAs('imagenes', $fileStore, 'public');

        // Devuelve una estructura de datos consistente con cómo se usa en store/update
        return response()->json(['data' => ['filename' => $fileStore, 'path' => Storage::url($path)], 'success' => true]);
    }

    private function subirMediaAWsp(UploadedFile $file) // Recibe un objeto UploadedFile
    {
        // Comprueba si las credenciales de WhatsApp están configuradas
        if (empty($this->phoneNumberId) || empty($this->accessToken)) {
            Log::error('CampanaController: WhatsApp Phone Number ID o Access Token no configurados para subir media.');
            return ['error' => 'Configuración de WhatsApp incompleta en el servidor.', 'status_code' => 500];
        }

        $url = "{$this->graphApiBaseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/media";

        try {
            $response = Http::withToken($this->accessToken) // Autenticación con token
            ->attach( // Adjunta el archivo a la petición
                'file', // Nombre del campo del archivo
                file_get_contents($file->getRealPath()), // Contenido del archivo
                $file->getClientOriginalName(), // Nombre original del archivo
                ['Content-Type' => $file->getMimeType()] // Tipo MIME del archivo
            )
                ->post($url, [ // Petición POST
                    'messaging_product' => 'whatsapp',
                ]);

            if ($response->failed()) { // Si la petición falla
                Log::error('CampanaController: Fallo al subir medio a WhatsApp.', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'url' => $url,
                ]);
                return ['error' => 'Fallo al subir el medio a WhatsApp', 'details' => $response->json(), 'status_code' => $response->status()];
            }

            $responseData = $response->json(); // Decodifica la respuesta JSON
            if (isset($responseData['id'])) { // Si la respuesta contiene un 'id'
                Log::info('CampanaController: Medio subido a WhatsApp exitosamente.', ['response' => $responseData]);
                return ['id' => $responseData['id'], 'status_code' => 200]; // Devuelve el ID
            } else {
                Log::error('CampanaController: ID del medio no encontrado en la respuesta de WhatsApp al subir.', ['response' => $responseData]);
                return ['error' => 'ID del medio no encontrado en la respuesta de WhatsApp', 'details' => $responseData, 'status_code' => $response->status() ?: 500];
            }

        } catch (\Exception $e) { // Captura cualquier excepción
            Log::error('CampanaController: Excepción durante la subida de medio a WhatsApp: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ['error' => 'Ocurrió una excepción durante la subida del medio', 'details' => $e->getMessage(), 'status_code' => 500];
        }
    }
}
