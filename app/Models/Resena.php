<?php

namespace App\Models;

use App\Http\Controllers\WhatsAppController;
use Google\Client;
use Google\Service\Calendar;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Google\Service\Calendar\Event as GoogleEvent;
class Resena extends Model
{
    use HasFactory;

    protected $appends = ['fecha','programado_en_formateado'];

    protected $fillable = [
        'nombre',
        'telefono',
        'email',
        'mensaje',
        'campanaId',
        'comercioId',
        'enviado',
        'programado_en',
        'google_calendar_event_id'
    ];

    protected $casts = [
        'created_at'        => 'datetime:d-m-Y H:i:s',
        'updated_at'        => 'datetime:d-m-Y H:i:s',
        'programado_en'     => 'datetime',
    ];

    /**
     * Get the Campana that owns the Resenas
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function campana(): BelongsTo
    {
        return $this->belongsTo(Campana::class, 'campanaId');
    }

    /**
     * Get the Comercio that owns the Resenas
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'comercioId');
    }

    public function getFechaAttribute() {
        return $this->created_at
            ->setTimezone('Europe/Madrid')
            ->format('d/m/Y H:i');
    }
    public function getProgramadoEnFormateadoAttribute()
    {
        if (!$this->programado_en) {
            return null;
        }

        // Traducción manual de los meses
        $meses = [
            'January' => 'enero',
            'February' => 'febrero',
            'March' => 'marzo',
            'April' => 'abril',
            'May' => 'mayo',
            'June' => 'junio',
            'July' => 'julio',
            'August' => 'agosto',
            'September' => 'septiembre',
            'October' => 'octubre',
            'November' => 'noviembre',
            'December' => 'diciembre',
        ];

        $fecha = $this->programado_en->setTimezone('Europe/Madrid');

        $dia = $fecha->format('j');
        $mesIngles = $fecha->format('F');
        $mes = $meses[$mesIngles];
        $anio = $fecha->format('Y');
        $hora = $fecha->format('H:i');

        return "$dia de $mes de $anio a las $hora";
    }

    public function procesarEnvio(WhatsAppController $whatsAppController, int $maxAttempts, Command $commandContext): bool
    {
        $campana = $this->campana;

        if (!$campana || !$campana->nombre_plantilla || !$campana->idImagenMeta) {
            $commandContext->warn("Solicitud ID {$this->id} sin campaña válida o plantilla/imagen. Revisar datos.");
            $this->registrarFallo('Datos de campaña incompletos o inválidos.', $maxAttempts, $commandContext);
            return false;
        }

        if (!preg_match('/^[0-9]{9,15}$/', $this->telefono)) {
            $commandContext->error("❌ Formato de teléfono inválido para ID {$this->id}: {$this->telefono}.");
            $this->registrarFallo('Formato de número de teléfono inválido detectado localmente.', $maxAttempts, $commandContext, true);
            return false;
        }

        $tiempoEnvioFinal = Carbon::parse($this->programado_en)
            ->addHours($campana->horasCampana)
            ->addMinutes($campana->minutosCampana);
        $intentoActual = $this->intentos_envio + 1;

        $commandContext->line("Enviando reseña ID {$this->id} (intento {$intentoActual}/{$maxAttempts}) a {$this->telefono} (Programación final: {$tiempoEnvioFinal->format('Y-m-d H:i:s')})...");

        $resultado = $whatsAppController->enviarMensajeTemplate(
            $this->telefono,
            $campana->nombre_plantilla,
            $campana->idImagenMeta
        );

        if ($resultado['success']) {
            $this->enviado = true;
            $this->ultimo_error_envio = null;
            $this->save();
            $commandContext->info("✅ Enviado correctamente. ID {$this->id}");
            $this->actualizarColorEventoGoogle('10');
            return true;
        } else {
            $this->registrarFallo($resultado['message'], $maxAttempts, $commandContext);
            // Si quisieras usar el error_code: $resultado['error_code'] está disponible aquí.
            return false;
        }
    }

    private function registrarFallo(string $mensajeError, int $maxAttempts, Command $commandContext, bool $marcarComoMaxDirectamente = false)
    {
        if ($marcarComoMaxDirectamente) {
            $this->intentos_envio = $maxAttempts;
        } else {
            $this->intentos_envio += 1;
        }
        $this->ultimo_error_envio = $mensajeError; // Aquí se guardará el mensaje más detallado
        $this->save();

        $logBase = "Reseña ID {$this->id}: ";
        if ($this->intentos_envio >= $maxAttempts) {
            $commandContext->warn($logBase . "Ha alcanzado el máximo de {$maxAttempts} intentos ({$mensajeError}) y no se reintentará automáticamente.");
        } else if (!$marcarComoMaxDirectamente) {
            $commandContext->error("❌ Fallo en el envío. {$logBase} (intento {$this->intentos_envio}/{$maxAttempts}): {$mensajeError}");
        }
    }
    public function actualizarColorEventoGoogle(string $colorId)
    {
        if (!$this->google_calendar_event_id || !$this->comercio || !$this->comercio->google_refresh_token) {
            Log::info("No se actualiza el color para la reseña {$this->id}: falta ID de evento o token.");
            return;
        }

        try {
            $client = new Client();
            $client->setClientId(env('GOOGLE_CLIENT_ID'));
            $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
            $client->fetchAccessTokenWithRefreshToken($this->comercio->google_refresh_token);

            $calendarService = new Calendar($client);

            // Creamos un objeto Evento solo con el ID del color que queremos cambiar
            $eventUpdate = new GoogleEvent([
                'colorId' => $colorId
            ]);

            // Usamos patch en lugar de update porque es más eficiente (solo envía los campos cambiados)
            $calendarService->events->patch('primary', $this->google_calendar_event_id, $eventUpdate);

            Log::info("Color del evento {$this->google_calendar_event_id} actualizado a '{$colorId}' para la reseña ID {$this->id}.");

        } catch (\Exception $e) {
            // Si falla, solo lo registramos. El proceso principal no debe detenerse por esto.
            Log::error("No se pudo actualizar el color del evento para la reseña ID {$this->id}: " . $e->getMessage());
        }
    }

}
