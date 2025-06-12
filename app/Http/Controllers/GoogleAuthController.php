<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use App\Models\GoogleCalendarChannel;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Channel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Carbon\Carbon;

class GoogleAuthController extends Controller
{
    /**
     * Inicia la redirección a Google para la autorización.
     */
    private function getGoogleClientForUser(Comercio $comercio): Client
    {
        if (!$comercio->google_refresh_token) {
            throw new \Exception('El comercio no tiene un refresh token.');
        }

        $client = new Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));

        // Pasamos el refresh token para que el cliente sepa cómo autenticarse
        $client->fetchAccessTokenWithRefreshToken($comercio->google_refresh_token);

        return $client;
    }

    /**
     * Inicia el flujo de autorización de OAuth 2.0.
     */
    public function redirect()
    {
        $client = new Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(route('google.auth.callback'));
        $client->setScopes([Calendar::CALENDAR_EVENTS]);
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');
        $client->setState(Auth::id());

        return response()->json(['authUrl' => $client->createAuthUrl()]);
    }

    /**
     * Maneja el callback de Google después de la autorización.
     */
    public function callback(Request $request)
    {
        $client = new Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(route('google.auth.callback'));

        try {
            $tokenResponse = $client->fetchAccessTokenWithAuthCode($request->input('code'));
            $comercio = Comercio::findOrFail($request->input('state'));

            $comercio->google_access_token = $tokenResponse['access_token'];
            if (isset($tokenResponse['refresh_token'])) {
                $comercio->google_refresh_token = $tokenResponse['refresh_token'];
            }
            $comercio->google_token_expires_at = Carbon::now()->addSeconds($tokenResponse['expires_in']);
            $comercio->save();

            return Redirect::to(env('FRONTEND_URL', 'http://localhost:4200') . '/calendario?status=success');

        } catch (\Exception $e) {
            Log::error('Error en el callback de Google: ' . $e->getMessage());
            return Redirect::to(env('FRONTEND_URL', 'http://localhost:4200') . '/calendario?status=error');
        }
    }

    /**
     * Inicia la vigilancia (webhook) para el calendario principal del usuario.
     */
    public function startWatching()
    {
        $comercio = Auth::user();

        try {
            $client = $this->getGoogleClientForUser($comercio);
            $calendarService = new Calendar($client);

            $channel = new Channel();
            $channel->setId(uniqid());
            $channel->setType('web_hook');
            $channel->setAddress(env('NGROK_URL') . '/api/google/webhook');

            $watchResponse = $calendarService->events->watch('primary', $channel);

            GoogleCalendarChannel::updateOrCreate(
                ['comercio_id' => $comercio->id],
                [
                    'channel_id' => $watchResponse->getId(),
                    'resource_id' => $watchResponse->getResourceId(),
                    'expires_at' => Carbon::createFromTimestampMs($watchResponse->getExpiration()),
                ]
            );

            // Obtenemos el primer syncToken
            $events = $calendarService->events->listEvents('primary');
            $comercio->google_sync_token = $events->getNextSyncToken();
            $comercio->save();

            return response()->json(['message' => 'Sincronización activada con éxito.']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al iniciar la vigilancia: ' . $e->getMessage()], 500);
        }
    }


    public function disconnect(Request $request)
    {
        $comercio = Auth::user();

        // Solo intentar operaciones con Google si el usuario tiene un token que revocar.
        if ($comercio->google_refresh_token) {

            // 1. Mueve la inicialización del cliente aquí.
            $client = new Client();
            $client->setClientId(env('GOOGLE_CLIENT_ID'));
            $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));

            // Bloque para detener los canales (si existen)
            $channels = GoogleCalendarChannel::where('comercio_id', $comercio->id)->get();
            if ($channels->isNotEmpty()) {
                try {
                    $client->fetchAccessTokenWithRefreshToken($comercio->google_refresh_token);
                    $calendarService = new Calendar($client);

                    foreach ($channels as $channel) {
                        try {
                            $calendarService->channels->stop(new Channel([
                                'id' => $channel->channel_id,
                                'resourceId' => $channel->resource_id
                            ]));
                        } catch (\Exception $e) {
                            Log::error("No se pudo detener el canal {$channel->channel_id}: " . $e->getMessage());
                        }
                        $channel->delete();
                    }
                } catch (\Exception $e) {
                    // Si fetchAccessTokenWithRefreshToken falla, lo registramos pero continuamos para revocar y limpiar.
                    Log::error("Error al obtener token para detener canales: " . $e->getMessage());
                }
            }

            // 2. Revocar el token (esta parte ahora funcionará siempre)
            try {
                // No es necesario volver a obtener el token para revocarlo.
                $revoked = $client->revokeToken($comercio->google_refresh_token);
                if (!$revoked) {
                    Log::warning("No se pudo revocar el token de {$comercio->id}. El token podría ya estar invalidado.");
                }
            } catch (\Exception $e) {
                Log::error("Error durante la revocación del token para el comercio {$comercio->id}: " . $e->getMessage());
            }
        }

        $comercio->google_access_token = null;
        $comercio->google_refresh_token = null;
        $comercio->google_token_expires_at = null;
        $comercio->google_sync_token = null;
        $comercio->save();

        return response()->json(['message' => 'Cuenta de Google desconectada y permisos revocados con éxito.']);
    }
}