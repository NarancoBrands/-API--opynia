<?php

namespace App\Http\Controllers;

use App\Models\Campana;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Illuminate\Http\Request;
use App\Models\Resena;
use App\Models\Comercio;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ResenasController extends Controller
{

    public function addResena(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'required|string',
            'mensaje' => 'nullable|string|max:255',
            'comercioId' => 'required|integer|exists:comercios,id',
            'programado_en' => 'required|date_format:Y-m-d H:i:s',
        ]);

        $comercio = Comercio::findOrFail($request->comercioId);
        $campanaId = $comercio->campana;

        if (!$campanaId || !Campana::where('id', $campanaId)->exists()) {
            return $this->sendError('La campaña asociada al comercio no existe.', [], 422);
        }

        $data = $request->all();
        $data['campanaId'] = $campanaId;
        $data['enviado'] = false;

        $resena = Resena::create($data);

        if ($comercio && $comercio->google_refresh_token) {
            try {
                $client = new Client();
                $client->setClientId(env('GOOGLE_CLIENT_ID'));
                $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
                $client->setAccessToken($comercio->google_access_token);

                if ($client->isAccessTokenExpired()) {
                    $tokenResponse = $client->fetchAccessTokenWithRefreshToken($comercio->google_refresh_token);
                    $client->setAccessToken($tokenResponse['access_token']);
                    $comercio->google_access_token = $tokenResponse['access_token'];
                    $comercio->google_token_expires_at = Carbon::now()->addSeconds($tokenResponse['expires_in']);
                    $comercio->save();
                }

                $calendarService = new Calendar($client);
                $event = new Event([
                    'summary' => 'Reseña: ' . $resena->nombre,
                    'description' => "Cliente: {$resena->nombre}\nTeléfono: {$resena->telefono}\nEmail: {$resena->email}\nMensaje: {$resena->mensaje}",
                    'start' => ['dateTime' => Carbon::parse($resena->programado_en)->toIso8601String(), 'timeZone' => 'Europe/Madrid'],
                    'end' => ['dateTime' => Carbon::parse($resena->programado_en)->addMinutes(30)->toIso8601String(), 'timeZone' => 'Europe/Madrid'],
                    'colorId' => '5'
                ]);

                $createdEvent = $calendarService->events->insert('primary', $event);

                // Guardamos el ID del evento de Google en nuestra reseña
                $resena->google_calendar_event_id = $createdEvent->getId();
                $resena->save();
                Log::info("Evento de Google Calendar creado ({$createdEvent->getId()}) para la reseña ID: {$resena->id}");

            } catch (\Exception $e) {
                // Si falla la creación del evento, solo lo registramos. No detenemos la creación de la reseña.
                Log::error("No se pudo crear el evento en Google Calendar para la reseña ID {$resena->id}: " . $e->getMessage());
            }
        }

        // Devolvemos la reseña creada (ahora podría tener el ID de Google)
        return $this->sendResponse($resena, 'Reseña creada con éxito.');
    }

    public function getResenasByIdComercio($id) {
        
        $resenas = Resena::where('comercioId', '=', $id)->get();

        return $this->sendResponse($resenas);

    }


    public function getStats()
    {
        $totalComercios = Comercio::count();
        $totalResenas = Resena::count();

        $resenasConvertidasCount = Resena::where('enviado', true)->count();
        $resenasSinConvertirCount = Resena::where('enviado', false)->count();

        $porcentajeConvertidas = 0;
        $porcentajeSinConvertir = 0;

        if ($totalResenas > 0) {
            $porcentajeConvertidas = ($resenasConvertidasCount / $totalResenas) * 100;
            $porcentajeSinConvertir = ($resenasSinConvertirCount / $totalResenas) * 100;
        }

        $stats = [
            'totalComercios' => $totalComercios,
            'totalResenas' => $totalResenas,
            'resenasConvertidasPorcentaje' => round($porcentajeConvertidas, 2),
            'resenasConvertidasNumero' => $resenasConvertidasCount,
            'resenasSinConvertirPorcentaje' => round($porcentajeSinConvertir, 2),
            'resenasSinConvertirNumero' => $resenasSinConvertirCount,
        ];

        return response()->json($stats);
    }

    /**
     * Helper para enviar respuestas JSON estandarizadas.
     * Puedes mover esto a un BaseController si lo usas en múltiples controladores.
     */


    public function destroy($id)
    {
        $resena = Resena::find($id);

        if (!$resena) {
            return $this->sendError('Reseña no encontrada.', [], 404);
        }

        // LÓGICA DE GOOGLE CALENDAR INTEGRADA
        // Si la reseña tiene un evento de Google asociado, intentamos borrarlo primero.
        if ($resena->google_calendar_event_id) {
            $comercio = $resena->comercio; // Obtenemos el comercio a través de la relación

            if ($comercio && $comercio->google_refresh_token) {
                try {
                    $client = new Client();
                    $client->setClientId(env('GOOGLE_CLIENT_ID'));
                    $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
                    $client->setAccessToken($comercio->google_access_token);

                    if ($client->isAccessTokenExpired()) {
                        $tokenResponse = $client->fetchAccessTokenWithRefreshToken($comercio->google_refresh_token);
                        $client->setAccessToken($tokenResponse['access_token']);
                        $comercio->google_access_token = $tokenResponse['access_token'];
                        $comercio->google_token_expires_at = Carbon::now()->addSeconds($tokenResponse['expires_in']);
                        $comercio->save();
                    }

                    $calendarService = new Calendar($client);
                    $calendarService->events->delete('primary', $resena->google_calendar_event_id);
                    Log::info("Evento de Google Calendar eliminado ({$resena->google_calendar_event_id}) para la reseña ID: {$resena->id}");

                } catch (\Exception $e) {
                    if ($e->getCode() !== 410) {
                        Log::error("No se pudo eliminar el evento de Google Calendar ({$resena->google_calendar_event_id}): " . $e->getMessage());
                    }
                }
            }
        }

        $resena->delete();

        return $this->sendResponse(null, 'Reseña eliminada correctamente.');
    }
    public function updateGoogleEventId(Request $request, Resena $resena)
    {
        try {
            $validatedData = $request->validate([
                'google_calendar_event_id' => 'required|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        }

        $resena->update([
            'google_calendar_event_id' => $validatedData['google_calendar_event_id'],
        ]);

        return response()->json([
            'message' => 'Event ID actualizado correctamente.',
            'data' => $resena->fresh()
        ], 200);
    }
}
