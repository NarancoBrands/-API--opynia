<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use App\Models\GoogleCalendarChannel;
use App\Models\Resena;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Calendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoogleWebhookController extends Controller
{
    /**
     * Maneja las notificaciones de Google Calendar.
     * Esta es la URL que Google llamará.
     */
    public function handle(Request $request)
    {
        $channelId = $request->header('X-Goog-Channel-ID');
        if (!$channelId) {
            return response()->json(['status' => 'ok']);
        }

        $channel = GoogleCalendarChannel::where('channel_id', $channelId)->first();
        if (!$channel) {
            return response()->json(['status' => 'ok']);
        }

        $comercioId = $channel->comercio_id;

        // Cooldown para evitar sincronizaciones excesivas
        $cooldownKey = 'webhook_cooldown_comercio_' . $comercioId;
        if (Cache::has($cooldownKey)) {
            Log::info("Webhook ignorado por cooldown para el comercio ID {$comercioId}.");
            return response()->json(['status' => 'ok']);
        }

        // Lock para evitar solapamiento
        $lock = Cache::lock('webhook_processing_comercio_' . $comercioId, 3);
        if (!$lock->get()) {
            Log::info("Ignorando webhook para comercio ID {$comercioId} (otro proceso ya en ejecución).");
            return response()->json(['status' => 'ok']);
        }

        // Establece cooldown (por ejemplo 30 segundos)
        Cache::put($cooldownKey, true, now()->addSeconds(30));

        try {
            if ($request->header('X-Goog-Resource-State') === 'exists') {
                $this->performSync($comercioId); // tu método que hace la sincronización real
                Log::info("Transacción completada para comercio ID: {$comercioId}");
            }
        } catch (\Exception $e) {
            Log::error('Error fatal en el handle del webhook: ' . $e->getMessage(), ['exception' => $e]);
        } finally {
            optional($lock)->release();
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Contiene la lógica principal de sincronización para un comercio específico.
     */
    private function performSync($comercioId)
    {
        DB::transaction(function () use ($comercioId) {
            $comercio = Comercio::lockForUpdate()->find($comercioId);
            if (!$comercio || !$comercio->google_refresh_token) {
                Log::error("Sincronización abortada para el comercio {$comercioId}: Faltan datos de conexión.");
                return;
            }

            try {
                $client = new Client();
                $client->setClientId(env('GOOGLE_CLIENT_ID'));
                $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
                $client->fetchAccessTokenWithRefreshToken($comercio->google_refresh_token);

                $calendarService = new Calendar($client);
                $syncOptions = ['syncToken' => $comercio->google_sync_token];

                try {
                    $events = $calendarService->events->listEvents('primary', $syncOptions);
                } catch (\Exception $e) {
                    if ($e->getCode() == 410) {
                        Log::warning("Sync token inválido para comercio ID: {$comercio->id}. Forzando sync completa.");
                        $comercio->google_sync_token = null; // Reseteamos para obtener uno nuevo
                        $events = $calendarService->events->listEvents('primary');
                    } else {
                        throw $e;
                    }
                }

                foreach ($events->getItems() as $event) {
                    if ($event->status === 'cancelled') {
                        $resena = Resena::where('google_calendar_event_id', $event->getId())->first();
                        if ($resena) {
                            $resena->delete();
                            Log::info("Reseña ID: {$resena->id} eliminada por cancelación en Google Calendar.");
                        }
                    } else {
                        if (empty($event->start->dateTime) || empty($event->getSummary()) || Resena::where('google_calendar_event_id', $event->getId())->exists()) {
                            continue;
                        }

                        // --- INICIO DE LA NUEVA LÓGICA MEJORADA ---
                        $summary = $event->getSummary();
                        $prefijo = 'Reseña:';

                        // 1. Filtro principal: Ignorar cualquier evento que no empiece por "Reseña:" (case-insensitive)
                        if (!str_starts_with(mb_strtolower($summary, 'UTF-8'), mb_strtolower($prefijo, 'UTF-8'))) {
                            continue; // Si no empieza con la palabra clave, saltamos al siguiente evento.
                        }

                        // 2. Si llegamos aquí, sabemos que el título es válido. Ahora extraemos los datos.
                        $datosExtraidos = ['nombre' => null, 'telefono' => null, 'email' => null, 'mensaje' => $event->getDescription() ?? ''];

                        // INTENTO A: Extraer NOMBRE y TELÉFONO directamente del título.
                        if (preg_match('/^Reseña:\s*(.*?)\s*Telefono:\s*(.*)$/i', $summary, $matches)) {
                            $datosExtraidos['nombre'] = trim($matches[1]);
                            $datosExtraidos['telefono'] = trim(str_replace(' ', '', $matches[2]));
                        } else {
                            // INTENTO B (Plan B): Si no hay teléfono en el título, extrae solo el nombre del título...
                            $datosExtraidos['nombre'] = trim(mb_substr($summary, mb_strlen($prefijo, 'UTF-8')));

                            // ...y busca el teléfono en la descripción.
                            if ($event->getDescription() && preg_match('/telef[oó]no\s*:\s*(.*)/i', $event->getDescription(), $matches)) {
                                $datosExtraidos['telefono'] = trim(str_replace(' ', '', $matches[1]));
                            }
                        }

                        // Extraemos el email de la descripción en cualquier caso (si existe)
                        if ($event->getDescription() && preg_match('/email\s*:\s*(.*)/i', $event->getDescription(), $matches)) {
                            $datosExtraidos['email'] = trim($matches[1]);
                        }
                        // --- FIN DE LA LÓGICA ---

                        if (empty($datosExtraidos['nombre'])) {
                            Log::warning("Omitiendo evento {$event->getId()} por no poder extraer el nombre del cliente.");
                            continue;
                        }

                        Log::info('Creando reseña para: ' . $datosExtraidos['nombre']);
                        Resena::create([
                            'google_calendar_event_id' => $event->getId(),
                            'nombre' => $datosExtraidos['nombre'],
                            'email' => $datosExtraidos['email'],
                            'mensaje' => $datosExtraidos['mensaje'],
                            'programado_en' => Carbon::parse($event->start->dateTime)->setTimezone('UTC'),
                            'telefono' => $datosExtraidos['telefono'] ?? '000000000',
                            'comercioId' => $comercio->id,
                            'enviado' => false,
                            'campanaId' => $comercio->campana,
                        ]);
                    }
                }

                $comercio->google_sync_token = $events->getNextSyncToken();
                $comercio->save();
                Log::info("Transacción de sincronización completada para comercio ID: {$comercio->id}.");

            } catch (\Exception $e) {
                Log::error('Error en performSync para comercio ID ' . $comercioId . ': ' . $e->getMessage());
            }
        });
    }
}