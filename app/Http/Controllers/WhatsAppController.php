<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WhatsAppController extends Controller
{ protected string $graphApiBaseUrl;
    protected string $apiVersion;
    protected ?string $phoneNumberId;
    protected ?string $accessToken;
    protected ?string $wabaId;
    protected ?string $appId;
    protected ?string $userAccessToken;

    public function __construct()
    {
        $this->graphApiBaseUrl = config('services.whatsapp.graph_api_base_url', 'https://graph.facebook.com');
        $this->apiVersion = config('services.whatsapp.api_version', 'v20.0');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->accessToken = config('services.whatsapp.access_token');
        $this->wabaId = config('services.whatsapp.waba_id');
        $this->appId = config('services.whatsapp.app_id');
        $this->userAccessToken = config('services.whatsapp.user_access_token');
    }

    /**
     * Envía un mensaje de plantilla de WhatsApp.
     */
    public function enviarMensajeTemplate(string $telefono, string $nombrePlantilla, string $idImagenMeta)
    {
        if (empty($this->phoneNumberId) || empty($this->accessToken)) {
            Log::error('WhatsAppController: Variables de entorno de WhatsApp no configuradas.');
            return ['success' => false, 'message' => 'Configuración de WhatsApp interna incompleta'];
        }

        $url = "{$this->graphApiBaseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $telefono,
            'type' => 'template',
            'template' => [
                'name' => $nombrePlantilla,
                'language' => ['code' => 'es'],
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'image',
                                'image' => ['id' => $idImagenMeta]
                            ]
                        ]
                    ],
                    [
                        'type' => 'body'
                    ]
                ]
            ]
        ];

        Log::info("WhatsAppController: Enviando payload a WhatsApp: ", $payload);

        $response = Http::withToken($this->accessToken)
            ->timeout(30)
            ->post($url, $payload);

        if ($response->failed()) {
            $responseData = $response->json();

            // El log actual es bueno, lo mantenemos.
            Log::error('Error al enviar mensaje de WhatsApp.', [
                'status' => $response->status(),
                'url' => $url,
                'payload' => $payload, // Loguear el payload puede ser útil para depurar
                'response_body' => $responseData,
                'response_headers' => $response->headers()
            ]);

            // **NUEVA FUNCIONALIDAD: Mejorar el mensaje de error y añadir código de error**
            $apiErrorMessage = 'Error al enviar mensaje vía WhatsApp. HTTP Status: ' . $response->status();
            $apiErrorCode = null;

            if (is_array($responseData) && isset($responseData['error'])) {
                $errorPayload = $responseData['error']; // $responseData['error'] debería ser un array
                if (is_array($errorPayload)) {
                    // Usamos el mensaje de error de la API si está disponible
                    $apiErrorMessage = $errorPayload['message'] ?? $apiErrorMessage;
                    $apiErrorCode = $errorPayload['code'] ?? null;
                }
            } elseif (!$responseData && $response->body()) {
                // Si la respuesta no fue JSON pero hubo un cuerpo, puede ser útil
                $apiErrorMessage = 'Respuesta no JSON de la API: ' . substr($response->body(), 0, 255);
            }

            return [
                'success'       => false,
                'message'       => $apiErrorMessage,      // Mensaje de error más específico
                'error_code'    => $apiErrorCode,         // Nuevo: Código de error de la API
                'response_data' => $responseData          // Respuesta completa de la API para depuración avanzada
            ];
        }

        return [
            'success'       => true,
            'message'       => 'Mensaje enviado correctamente.', // Mensaje de éxito opcional
            'response_data' => $response->json() // Respuesta completa de la API
        ];
    }
    /**
     * Crea una plantilla de mensaje de WhatsApp.
     */
    public function crearPlantilla(Request $request)
    {

        $validatedData = $request->validate([
            'name' => 'required|string|max:512|regex:/^[a-z0-9_]+$/', // Nombre de la plantilla
            'language' => 'required|string', // Código de idioma, ej: "es", "en_US"
            'category' => ['required', 'string', Rule::in(['MARKETING', 'UTILITY', 'AUTHENTICATION'])],
            'allow_category_change' => 'sometimes|boolean', // Opcional

            'components' => 'required|array|min:1', // El array 'components' es obligatorio
            'components.*.type' => ['required', 'string', Rule::in(['HEADER', 'BODY', 'FOOTER', 'BUTTONS'])],

            // Validación para componente HEADER
            'components.*.format' => ['required_if:components.*.type,HEADER', 'nullable', 'string', Rule::in(['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'])],
            'components.*.text' => 'required_if:components.*.type,BODY,FOOTER|required_if:components.*.format,TEXT|nullable|string|max:1024', // Max 1024 para BODY, 60 para HEADER/FOOTER text
            'components.*.example.header_handle' => 'required_if:components.*.format,IMAGE,VIDEO,DOCUMENT|nullable|array|max:1', // Espera un array con un handle
            'components.*.example.header_handle.0' => 'sometimes|string',
            'components.*.example.header_text' => 'required_if:components.*.format,TEXT|nullable|array', // Para variables en header text
            'components.*.example.header_text.*' => 'sometimes|string',
            // 'components.*.example.header_url' => '...', // Si permites URL de ejemplo para media en header

            // Validación para componente BODY
            // El texto del BODY ya está cubierto por 'components.*.text'
            'components.*.example.body_text' => 'sometimes|array', // Array de arrays para ejemplos de variables
            'components.*.example.body_text.*' => 'sometimes|array',
            'components.*.example.body_text.*.*' => 'sometimes|string',

            // Validación para componente BUTTONS
            'components.*.buttons' => 'required_if:components.*.type,BUTTONS|nullable|array|min:1', // Debe tener al menos 1 botón si el componente es BUTTONS
            'components.*.buttons.*.type' => ['required_if:components.*.type,BUTTONS', 'string', Rule::in(['QUICK_REPLY', 'URL', 'PHONE_NUMBER'])],
            'components.*.buttons.*.text' => 'required_if:components.*.type,BUTTONS|string|max:25', // Límite de WhatsApp
            'components.*.buttons.*.url' => 'required_if:components.*.buttons.*.type,URL|nullable|string|url',
            'components.*.buttons.*.phone_number' => 'required_if:components.*.buttons.*.type,PHONE_NUMBER|nullable|string',
            // 'components.*.buttons.*.example' => '...', // Para ejemplos en botones URL dinámicos

            // 'components.*.example' para FOOTER (si aplica y tiene variables)
            // ...
        ]);

        // Verificar configuración esencial del controlador
        if (empty($this->wabaId) || empty($this->accessToken)) {
            Log::error('WhatsAppController: Variables de entorno (WABA_ID o ACCESS_TOKEN) no configuradas para crear plantillas.');
            return response()->json(['error' => 'Configuración de WhatsApp incompleta en el servidor.'], 500);
        }

        // La URL para crear plantillas usa el WABA ID (WhatsApp Business Account ID)
        $url = "{$this->graphApiBaseUrl}/{$this->apiVersion}/{$this->wabaId}/message_templates";

        // La lógica de construir 'components' a partir de campos individuales se elimina.
        $payload = [
            'name' => $validatedData['name'],
            'language' => $validatedData['language'], // El frontend ya debería enviar el idioma correcto
            'category' => $validatedData['category'],
            'components' => $validatedData['components'], // Se pasa directamente el array de componentes validado
        ];


        if (isset($validatedData['allow_category_change'])) {
            $payload['allow_category_change'] = (bool) $validatedData['allow_category_change'];
        } else {
            $payload['allow_category_change'] = true;
        }


        Log::info("WhatsAppController: Payload para crear plantilla WhatsApp (desde frontend con 'components'): " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // 3. REALIZAR LA LLAMADA A LA API DE META
        $response = Http::withToken($this->accessToken)
            ->timeout(30) // Tiempo de espera para la respuesta
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error('WhatsAppController: Error al crear plantilla de WhatsApp en Meta API.', [
                'status' => $response->status(),
                'response_body' => $response->body(), // Usar body() para obtener el cuerpo como string si json() falla
                'url_meta' => $url,
                'payload_sent_to_meta' => $payload
            ]);
            // Intenta decodificar como JSON, pero si falla, usa el cuerpo del error como string.
            $details = $response->json();
            if (is_null($details)) {
                $details = ['meta_error_raw' => $response->body()];
            }
            return response()->json(['error' => 'Error al crear la plantilla en WhatsApp', 'details' => $details, 'payload_sent_to_meta_on_error' => $payload], $response->status());
        }

        Log::info('WhatsAppController: Plantilla de WhatsApp creada/actualizada exitosamente en Meta API.', ['response' => $response->json()]);
        return response()->json($response->json()); // Devuelve la respuesta de Meta
    }

    /**
     * Sube un archivo multimedia a WhatsApp para ser usado en mensajes (no para ejemplos de plantillas).
     * Devuelve el ID del medio.
     */
    public function subirMedia(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpeg,png,jpg,webp,mp4,amr,aac,ogg|max:16384', // Max 16MB para video, 5MB para imagen
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Fallo de validación del archivo', 'details' => $validator->errors()], 422);
        }

        if (empty($this->phoneNumberId) || empty($this->accessToken)) {
            Log::error('WhatsAppController: WhatsApp Phone Number ID o Access Token no configurados para subir media.');
            return response()->json(['error' => 'Configuración de WhatsApp incompleta en el servidor.'], 500);
        }

        $file = $request->file('file');
        $url = "{$this->graphApiBaseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/media";

        try {
            $response = Http::withToken($this->accessToken)
                ->attach(
                    'file',
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName(),
                    ['Content-Type' => $file->getMimeType()]
                )
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    // 'type' => $file->getMimeType() // Opcional, Meta suele inferirlo
                ]);

            if ($response->failed()) {
                Log::error('WhatsAppController: Fallo al subir medio a WhatsApp.', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'url' => $url,
                ]);
                return response()->json(['error' => 'Fallo al subir el medio a WhatsApp', 'details' => $response->json()], $response->status());
            }

            $responseData = $response->json();
            if (isset($responseData['id'])) {
                Log::info('WhatsAppController: Medio subido a WhatsApp exitosamente.', ['response' => $responseData]);
                return response()->json(['id' => $responseData['id']], 200); // Este es el ID del medio, NO el handle 'h:'
            } else {
                Log::error('WhatsAppController: ID del medio no encontrado en la respuesta de WhatsApp al subir.', ['response' => $responseData]);
                return response()->json(['error' => 'ID del medio no encontrado en la respuesta de WhatsApp', 'details' => $responseData], 500);
            }

        } catch (\Exception $e) {
            Log::error('WhatsAppController: Excepción durante la subida de medio a WhatsApp: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Ocurrió una excepción durante la subida del medio', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Sube un archivo multimedia a Meta para obtener un "handle" (h:xxxx)
     * que se usa en los ejemplos de componentes de plantillas (header_handle).
     * Este es un proceso de subida resumible en dos pasos.
     */
    public function obtenerHandleParaPlantilla(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Documentación de Meta: Imágenes (JPEG, PNG) <5MB, Documentos (PDF) <100MB, Videos (MP4 con H.264 video & AAC audio) <16MB
            'file' => 'required|file|mimes:jpeg,png,jpg,pdf,mp4|max:102400', // Max 100MB para PDF como ejemplo general
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Fallo de validación del archivo para handle de plantilla', 'details' => $validator->errors()], 422);
        }

        if (empty($this->appId)  || empty($this->userAccessToken)) {
            Log::error('WhatsAppController: Meta App ID o User Access Token para subidas de app no configurados.');
            return response()->json(['error' => 'Configuración de Meta App para subidas incompleta.'], 500);
        }

        $file = $request->file('file');
        $file_path = $file->getRealPath();
        $file_name = $file->getClientOriginalName();
        $file_length = $file->getSize();
        $file_type = $file->getMimeType();

        $allowed_mime_types = ['image/jpeg', 'image/png', 'application/pdf', 'video/mp4'];
        if (!in_array($file_type, $allowed_mime_types)) {
            return response()->json(['error' => 'Tipo de archivo no permitido para ejemplos de plantilla.', 'details' => "El tipo {$file_type} no es válido."], 422);
        }

        try {
            // PASO 1: Iniciar Sesión de Subida
            $initUploadUrl = "{$this->graphApiBaseUrl}/{$this->apiVersion}/{$this->appId}/uploads";
            $initResponse = Http::post($initUploadUrl, [
                'file_length' => $file_length,
                'file_type' => $file_type,
                'file_name' => $file_name,
                'access_token' => $this->userAccessToken,
            ]);

            if ($initResponse->failed()) {
                Log::error("WhatsAppController: Paso 1 (Iniciar Subida) para handle falló.", [
                    'status' => $initResponse->status(),
                    'response' => $initResponse->body(),
                    'app_id' => $this->appId,
                    'file_name' => $file_name,
                    'file_type' => $file_type,
                ]);
                return response()->json(['error' => 'Fallo al iniciar la sesión de subida con Meta', 'details' => $initResponse->json() ?? $initResponse->body()], $initResponse->status());
            }

            $upload_session_id = $initResponse->json('id');
            if (empty($upload_session_id)) {
                Log::error("WhatsAppController: Paso 1 (Iniciar Subida) para handle no devolvió upload_session_id.", ['response' => $initResponse->body()]);
                return response()->json(['error' => 'No se obtuvo el ID de sesión de subida de Meta'], 500);
            }
            Log::info("WhatsAppController: Paso 1 para handle exitoso. Upload Session ID: {$upload_session_id}");

            // PASO 2: Subir el Archivo Binario
            $uploadFileUrl = "{$this->graphApiBaseUrl}/{$this->apiVersion}/{$upload_session_id}"; // El ID de sesión ya contiene el prefijo "upload:"

            $uploadResponse = Http::withHeaders([
                'Authorization' => 'OAuth ' . $this->userAccessToken, // Token de acceso de la app o usuario
                'file_offset' => '0', // Para archivos pequeños, el offset es 0
            ])
                ->withBody(file_get_contents($file_path), $file_type)
                ->post($uploadFileUrl);

            if ($uploadResponse->failed()) {
                Log::error("WhatsAppController: Paso 2 (Subir Archivo) para handle falló.", [
                    'status' => $uploadResponse->status(),
                    'response' => $uploadResponse->body(),
                    'upload_session_id' => $upload_session_id
                ]);
                return response()->json(['error' => 'Fallo al subir el archivo binario a Meta', 'details' => $uploadResponse->json() ?? $uploadResponse->body()], $uploadResponse->status());
            }

            $fileHandleData = $uploadResponse->json();
            if (isset($fileHandleData['h'])) {
                Log::info("WhatsAppController: Paso 2 para handle exitoso. Archivo subido a Meta para plantilla, handle: {$fileHandleData['h']}");
                return response()->json(['header_handle' => $fileHandleData['h']], 200); // Este es el handle 'h:xxxx'
            } else {
                Log::error("WhatsAppController: Paso 2 (Subir Archivo) para handle no devolvió el handle 'h'.", ['response' => $uploadResponse->body()]);
                return response()->json(['error' => "No se obtuvo el handle 'h' del archivo subido desde Meta", 'details' => $fileHandleData], 500);
            }

        } catch (\Exception $e) {
            Log::error('WhatsAppController: Excepción durante la subida de archivo para obtener handle: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Ocurrió una excepción durante la subida del archivo para plantilla', 'details' => $e->getMessage()], 500);
        }
    }

    public function obtenerEstadoPlantilla(string $templateName)
    {
        // Verificar configuración esencial del controlador
        if (empty($this->wabaId) || empty($this->accessToken)) {
            Log::error('WhatsAppController: WABA_ID o ACCESS_TOKEN no configurados para obtener estado de plantilla.');
            return [
                'success' => false,
                'message' => 'Configuración de WhatsApp (WABA ID o Token) incompleta en el servidor.',
                'status' => null, 'reason' => null, 'template_id' => null
            ];
        }
        if (empty($templateName)) {
            Log::error('WhatsAppController: Nombre de plantilla no proporcionado para obtenerEstadoPlantilla.');
            return [
                'success' => false,
                'message' => 'Nombre de plantilla no proporcionado.',
                'status' => null, 'reason' => null, 'template_id' => null
            ];
        }

        $languageCode = 'es'; // Idioma siempre es 'es' según el requerimiento
        $url = "{$this->graphApiBaseUrl}/{$this->apiVersion}/{$this->wabaId}/message_templates";

        try {
            Log::info("WhatsAppController: Solicitando estado para plantilla '{$templateName}' (idioma: {$languageCode}) en WABA ID {$this->wabaId}");

            $response = Http::withToken($this->accessToken)
                ->timeout(30) // Tiempo de espera para la respuesta
                ->get($url, [
                    'name' => $templateName,
                    'language' => $languageCode,
                    'fields' => 'name,status,language,id,quality_score,rejected_reason' // Campos deseados
                ]);

            if ($response->failed()) {
                $responseData = $response->json();
                $apiErrorMessage = 'Error de API al obtener estado de plantilla.';
                $apiErrorCode = null;

                if (is_array($responseData) && isset($responseData['error']) && is_array($responseData['error'])) {
                    $apiErrorMessage = $responseData['error']['message'] ?? $apiErrorMessage;
                    $apiErrorCode = $responseData['error']['code'] ?? null;
                }

                Log::error("WhatsApp API Error al obtener estado de plantilla '{$templateName}' ({$languageCode}) para WABA {$this->wabaId}:", [
                    'status_code' => $response->status(),
                    'error_message' => $apiErrorMessage,
                    'error_code' => $apiErrorCode,
                    'response_data' => $responseData
                ]);
                return [
                    'success'       => false,
                    'message'       => $apiErrorMessage,
                    // 'error_code'    => $apiErrorCode, // Opcional si el consumidor lo va a usar
                    'status'        => null,
                    'reason'        => null,
                    'template_id'   => null
                ];
            }

            $templatesData = $response->json();

            if (isset($templatesData['data']) && !empty($templatesData['data'])) {
                // Asumimos que la combinación de WABA ID, nombre e idioma 'es' es única y devuelve una plantilla.
                $templateInfo = $templatesData['data'][0];
                $status = $templateInfo['status'] ?? null;
                // El campo para la razón de rechazo es 'rejected_reason'
                $reason = $templateInfo['rejected_reason'] ?? null;
                $templateId = $templateInfo['id'] ?? null;

                Log::info("WhatsAppController: Plantilla '{$templateName}' ({$languageCode}) encontrada. Estado: {$status}, Razón rechazo: {$reason}, ID: {$templateId}");

                return [
                    'success'     => true,
                    'status'      => $status,
                    'reason'      => $reason,
                    'template_id' => $templateId,
                    'message'     => 'Estado de plantilla obtenido correctamente.'
                ];
            } else {
                // La llamada a la API fue exitosa (HTTP 200), pero no se encontraron datos para esa plantilla.
                // Esto usualmente significa que la plantilla con ese nombre e idioma no existe en esa WABA.
                Log::warning("WhatsAppController: Plantilla '{$templateName}' ({$languageCode}) no encontrada para WABA ID {$this->wabaId} (o la respuesta de API está vacía).", [
                    'response' => $templatesData
                ]);
                return [
                    'success'     => true, // La llamada API fue exitosa
                    'status'      => null, // Indica que la plantilla no fue encontrada o no tiene estado.
                    'reason'      => null,
                    'template_id' => null,
                    'message'     => "Plantilla '{$templateName}' ({$languageCode}) no encontrada para WABA ID {$this->wabaId}."
                ];
            }

        } catch (\Exception $e) {
            Log::error("WhatsAppController: Excepción al obtener estado de plantilla '{$templateName}' ({$languageCode}) para WABA ID {$this->wabaId}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return [
                'success'       => false,
                'message'       => 'Excepción durante la llamada a la API para obtener estado: ' . $e->getMessage(),
                'status'        => null,
                'reason'        => null,
                'template_id'   => null
            ];
        }
    }
}
