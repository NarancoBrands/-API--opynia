<?php

namespace App\Console\Commands;

use App\Http\Controllers\WhatsAppController;
use App\Models\Resena;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

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
        $this->info('Buscando solicitudes de reseña pendientes y programadas para envío...');

        $now = now();
        $maxAttempts = Config::get('whatsapp.max_send_attempts', 3);

        $solicitudesParaEnviar = Resena::select('resenas.*')
            ->join('campanas', 'resenas.campanaId', '=', 'campanas.id')
            ->where('resenas.enviado', false)
            ->where('resenas.intentos_envio', '<', $maxAttempts)
            ->whereNotNull('resenas.programado_en')
            ->whereNotNull('campanas.nombre_plantilla')
            ->whereNotNull('campanas.idImagenMeta')
            ->whereRaw('DATE_ADD(DATE_ADD(resenas.programado_en, INTERVAL campanas.horasCampana HOUR), INTERVAL campanas.minutosCampana MINUTE) <= ?', [$now])
            ->with('campana')
            ->get();

        if ($solicitudesParaEnviar->isEmpty()) {
            $this->info('No hay solicitudes de reseña programadas para envío inmediato o todas las pendientes han alcanzado el límite de reintentos.');
            return 0;
        }

        $this->info("Se encontraron {$solicitudesParaEnviar->count()} solicitudes para enviar ahora.");
        $contadorEnviados = 0;

        foreach ($solicitudesParaEnviar as $solicitud) {
            if ($solicitud->procesarEnvio($this->whatsAppController, $maxAttempts, $this)) {
                $contadorEnviados++;
            }
        }

        $this->info("Proceso completado. Total enviados: {$contadorEnviados}");
        return 0;
    }
}
