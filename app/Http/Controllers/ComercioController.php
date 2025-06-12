<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comercio;
use App\Models\Campana;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;


class ComercioController extends Controller
{
    private $graphApiBaseUrl;
    private $apiVersion;
    private $phoneNumberId;
    private $accessToken;

    public function __construct()
    {
        $this->graphApiBaseUrl = config('services.whatsapp.graph_api_base_url');
        $this->apiVersion = config('services.whatsapp.api_version');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->accessToken = config('services.whatsapp.access_token');
        $this->wabaId = config('services.whatsapp.waba_id');
    }
    /**
     * Almacena un nuevo comercio en la base de datos.
     */
    public function store(Request $request)
    {
        // Validar los datos de entrada
        $validatedData = $request->validate([
            // Campos de Comercio
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
            'telefono' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:comercios,email', // Es común que el email sea único
            'password' => 'required|string', // Considera una longitud mínima para password
            'password_confirmation' => 'required|same:password',
            'horas' => 'required|integer',
            'minutos' => 'required|integer',
            'activo' => 'nullable|boolean',
            'bloqueado' => 'nullable|boolean',
            'cuenta' => 'required|string|max:255',

            // Campos de Campana
            'tituloCampana' => 'required|string|max:255',
            'mensajeCampana' => 'required|string|max:255',
            // Validación de imagen mejorada como en tu segundo ejemplo
            'imagenCampana' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:20480', // max:20MB
            'horasCampana' => 'required|integer',
            'minutosCampana' => 'required|integer',
            'tipoBoton' => 'nullable|string|max:255',
            'textoBoton' => 'nullable|string|max:255',
            'urlBoton' => 'nullable|string|max:255'
        ]);

        // Iniciar la transacción de la base de datos
        DB::beginTransaction();

        try {
            // Crear el comercio en la base de datos
            $comercio = Comercio::create([
                'nombre' => $validatedData['nombre'],
                'direccion' => $validatedData['direccion'],
                'telefono' => $validatedData['telefono'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'token' => Hash::make($validatedData['email'] . now()), // Token único
                'horas' => $validatedData['horas'],
                'minutos' => $validatedData['minutos'],
                'activo' => $validatedData['activo'] ?? true,
                'bloqueado' => $validatedData['bloqueado'] ?? false,
                'cuenta' => $validatedData['cuenta'],
            ]);

            // Preparar datos para la Campana
            $campanaData = [
                'tituloCampana' => $validatedData['tituloCampana'],
                'mensajeCampana' => $validatedData['mensajeCampana'],
                'idComercio' => $comercio->id, // Asociar con el comercio recién creado
                'horasCampana' => $validatedData['horasCampana'],
                'minutosCampana' => $validatedData['minutosCampana'],
                'imagenCampana' => null, // Valor por defecto
                'idImagenMeta' => null,  // Nuevo campo, valor por defecto
                'estado' => 'PENDIENTE', // Nuevo campo, estado por defecto
                'tipoBoton' =>  $validatedData['tipoBoton'],
                'textoBoton' =>  $validatedData['textoBoton'],
                'urlBoton' =>  $validatedData['urlBoton'],
            ];

            if ($request->hasFile('imagenCampana')) {
                $fileToUpload = $request->file('imagenCampana');

                $localImageUploadResponse = $this->addPhoto($request, 'imagenCampana')->getData();
                if (!$localImageUploadResponse->success) {
                    DB::rollBack();
                    return response()->json([
                        'message' => $localImageUploadResponse->message ?? 'Error al subir imagen local.',
                        'success' => false
                    ], 400);
                }
                $campanaData['imagenCampana'] = $localImageUploadResponse->data->filename;

                // Subida a WhatsApp (Meta)
                $whatsappMediaUploadResponse = $this->subirMediaAWsp($fileToUpload);
                if (isset($whatsappMediaUploadResponse['id'])) {
                    $campanaData['idImagenMeta'] = $whatsappMediaUploadResponse['id'];
                } else {
                    Log::error('Fallo al subir medio a WhatsApp o obtener ID durante la creación de campaña para comercio.', [
                        'comercio_id' => $comercio->id,
                        'response' => $whatsappMediaUploadResponse
                    ]);

                }
            }

            // Crear la campaña asociada al comercio
            $campana = Campana::create($campanaData);

            // Generar el nombre de plantilla
            $tituloNormalizado = strtolower($validatedData['tituloCampana']);
            $tituloNormalizado = preg_replace('/[^a-z0-9_]/', '_', $tituloNormalizado);
            $tituloNormalizado = substr($tituloNormalizado, 0, 30); // Acortar si es muy largo
            // Asegurar unicidad si es necesario, aunque el ID de campaña ya ayuda
            $nombrePlantilla = "plantilla_{$tituloNormalizado}_{$campana->id}";

            $campana->nombre_plantilla = $nombrePlantilla;
            $campana->save();

            $comercio->update([
                'campana' => $campana->id // Asegúrate que la columna 'campana' exista en la tabla 'comercios'
            ]);

            // Confirmar la transacción
            DB::commit();

            // Cargar la relación para la respuesta si es necesario
            $campana->load('comercio');

            return response()->json([
                'message' => 'Comercio y campaña creados con éxito',
                'comercio' => $comercio,
                'campana' => $campana,
                'success' => true // Añadido para consistencia con tu segundo ejemplo
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Si ocurre un error de validación (aunque ya lo maneja Laravel por defecto)
            DB::rollBack();
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
                'success' => false
            ], 422);
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, hacer rollback de la transacción
            DB::rollBack();
            Log::error("Error al crear comercio y campaña: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => 'Error al crear el comercio y la campaña.',
                'error' => $e->getMessage(),
                'success' => false
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
            'campanaId' => 'required|integer'
        ]);

        // Preparamos los datos para la actualización
        $updateData = $validatedData;
        if (isset($validatedData['campanaId'])) {
            $updateData['campana'] = $validatedData['campanaId']; // Mapeamos a 'campana'
            unset($updateData['campanaId']); // Quitamos campanaId porque no es una columna directa
        }

        // Actualizar los datos
        $comercio->update($updateData); // Ahora $updateData tiene ['campana' => 12, ...]

        // Es una buena práctica refrescar el modelo desde la BD
        // para asegurar que la respuesta contiene el estado más reciente.
        $comercio->refresh();

        return response()->json([
            'message' => 'Comercio actualizado con éxito',
            'comercio' => $comercio // $comercio->campana ahora debería ser 12
        ]);
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

    private function addPhoto(Request $request, $name = 'imagenCampana')
    {
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
}
