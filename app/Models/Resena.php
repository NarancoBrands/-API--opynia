<?php

namespace App\Models;

use App\Http\Controllers\WhatsAppController;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resena extends Model
{
    use HasFactory;

    protected $appends = ['fecha'];

    protected $fillable = [
        'nombre',
        'telefono',
        'email',
        'mensaje',
        'campanaId',
        'comercioId',
        'enviado',
        'programado_en'
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
        return $this->created_at->format('d/m/y');
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

        // Se llama al método modificado del WhatsAppController
        $resultado = $whatsAppController->enviarMensajeTemplate(
            $this->telefono,
            $campana->nombre_plantilla,
            $campana->idImagenMeta
        );

        if ($resultado['success']) {
            $this->enviado = true;
            $this->ultimo_error_envio = null; // Limpiar el último error en caso de éxito
            $this->save();
            $commandContext->info("✅ Enviado correctamente. ID {$this->id}");
            return true;
        } else {
            // $resultado['message'] ahora será más específico gracias a los cambios en el controlador
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
}
