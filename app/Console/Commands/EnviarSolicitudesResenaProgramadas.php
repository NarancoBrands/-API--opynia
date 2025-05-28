<?php

namespace App\Console\Commands;

use App\Http\Controllers\WhatsAppController;
use App\Models\Resena;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class EnviarSolicitudesResenaProgramadas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:enviar-resenas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía las solicitudes de reseña pendientes si ya pasó el tiempo programado.';

    /**
     * Execute the console command.
     */
    protected $whatsAppController;

    public function __construct(WhatsAppController $whatsAppController)
    {
        parent::__construct();
        $this->whatsAppController = $whatsAppController;
    }

    public function handle()
    {
        $this->info('Buscando solicitudes de reseña pendientes de envío...');

        $solicitudesPendientes = Resena::where('enviado', false)->with('campana')->get();

        if ($solicitudesPendientes->isEmpty()) {
            $this->info('No hay solicitudes de reseña pendientes.');
            return 0;
        }

        $contadorEnviados = 0;

        foreach ($solicitudesPendientes as $solicitud) {
            $campana = $solicitud->campana;

            if (!$campana || !$campana->nombre_plantilla || !$campana->idImagenMeta) {
                $this->warn("Solicitud ID {$solicitud->id} sin campaña válida o plantilla/imagen no configuradas.");
                continue;
            }

            $tiempoEnvio = $solicitud->created_at
                ->addHours($campana->horasCampana)
                ->addMinutes($campana->minutosCampana);

            if (now()->gte($tiempoEnvio)) {
                $this->line("Enviando reseña ID {$solicitud->id} a {$solicitud->telefono}...");

                $resultado = $this->whatsAppController->enviarMensajeTemplate(
                    $solicitud->telefono,
                    $campana->nombre_plantilla,
                    $campana->idImagenMeta
                );

                if ($resultado['success']) {
                    $solicitud->enviado = true;
                    $solicitud->save();
                    $this->info("✅ Enviado correctamente. ID {$solicitud->id}");
                    $contadorEnviados++;
                } else {
                    $this->error("❌ Fallo en el envío. ID {$solicitud->id}: " . $resultado['message']);
                }
            } else {
                $this->line("Solicitud ID {$solicitud->id} aún no está programada para envío. Programada para {$tiempoEnvio}");
            }
        }

        $this->info("Proceso completado. Total enviados: {$contadorEnviados}");
        return 0;
    }
}
