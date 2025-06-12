<?php

namespace App\Console\Commands;

use App\Http\Controllers\WhatsAppController;
use App\Models\Campana;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ActualizarEstadosPlantillaWhatsapp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:actualizar-estados-plantilla';
    protected WhatsAppController $whatsAppController;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica y actualiza el estado de las plantillas de WhatsApp asociadas a campañas pendientes.';

    public function __construct(WhatsAppController $whatsAppController)
    {
        parent::__construct();
        $this->whatsAppController = $whatsAppController;
    }
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando proceso de actualización de estados de plantillas de WhatsApp...');
        Log::info('Tarea programada: Iniciando whatsapp:actualizar-estados-plantilla');

        $campanasPendientes = Campana::where(function ($query) {
            $query->where('estado', 'PENDIENTE')
                ->orWhereNull('estado');
        })
            ->whereNotNull('nombre_plantilla') // Importante: solo si tienen nombre de plantilla
            ->get();

        if ($campanasPendientes->isEmpty()) {
            $this->info('No hay campañas con plantillas pendientes de aprobación para verificar.');
            Log::info('Tarea programada: No hay campañas pendientes.');
            return Command::SUCCESS;
        }

        $this->info("Se encontraron {$campanasPendientes->count()} campañas con plantillas pendientes de aprobación.");
        $actualizadas = 0;
        $fallidasVerificacion = 0;

        foreach ($campanasPendientes as $campana) {
            if (empty($campana->nombre_plantilla)) {
                $this->warn("Campaña ID {$campana->id} está PENDIENTE_APROBACION pero no tiene nombre_plantilla. Omitiendo.");
                Log::warning("Campaña ID {$campana->id} en PENDIENTE_APROBACION sin nombre_plantilla.");
                continue;
            }

            $this->line("Verificando estado de plantilla '{$campana->nombre_plantilla}' para campaña ID {$campana->id} (Estado actual: {$campana->estado})...");

            $resultadoApi = $this->whatsAppController->obtenerEstadoPlantilla($campana->nombre_plantilla);

            if (!$resultadoApi['success']) {
                $this->error("Error al verificar plantilla para campaña ID {$campana->id}: {$resultadoApi['message']}");
                Log::error("Error API al verificar plantilla '{$campana->nombre_plantilla}' para campaña ID {$campana->id}: {$resultadoApi['message']}");
                $fallidasVerificacion++;
                continue;
            }

            $nuevoEstadoMeta = $resultadoApi['status'];
            $motivoRechazoApi = $resultadoApi['reason'];
            $templateIdMeta = $resultadoApi['template_id'];

         //   if (empty($campana->whatsapp_template_id) && !empty($templateIdMeta)) {
           //     $campana->whatsapp_template_id = $templateIdMeta;
           // }

            if ($nuevoEstadoMeta === 'APPROVED') {
                if ($campana->estado !== 'ACTIVA') {
                    $campana->estado = 'ACTIVA';
                    $campana->save();
                    $this->info("✅ Campaña ID {$campana->id}: Plantilla '{$campana->nombre_plantilla}' APROBADA. Campaña actualizada a ACTIVA.");
                    Log::info("Campaña ID {$campana->id} actualizada a ACTIVA. Plantilla '{$campana->nombre_plantilla}' APROBADA (ID Meta: {$templateIdMeta}).");
                    $actualizadas++;
                } else {
                    $this->line("Campaña ID {$campana->id}: Plantilla ya estaba APROBADA y campaña ACTIVA. Sin cambios.");
                }
            } elseif ($nuevoEstadoMeta === 'REJECTED') {
                if ($campana->estado !== 'RECHAZADA') {
                    $campana->estado = 'RECHAZADA';
                    $campana->save();
                    $this->warn("❌ Campaña ID {$campana->id}: Plantilla '{$campana->nombre_plantilla}' RECHAZADA. Campaña actualizada a RECHAZADA. (Razón API: {$motivoRechazoApi})");
                    Log::warning("Campaña ID {$campana->id} actualizada a RECHAZADA. Plantilla '{$campana->nombre_plantilla}' RECHAZADA (ID Meta: {$templateIdMeta}). Razón API: {$motivoRechazoApi}");
                    $actualizadas++;
                } else {
                    $this->line("Campaña ID {$campana->id}: Plantilla ya estaba RECHAZADA. Sin cambios.");
                }
            } elseif ($nuevoEstadoMeta === null && $resultadoApi['message'] && str_contains($resultadoApi['message'], 'no encontrada')) {
                $this->line("Plantilla '{$campana->nombre_plantilla}' para campaña ID {$campana->id} no encontrada en Meta. Se mantiene PENDIENTE_APROBACION.");
                Log::info("Plantilla '{$campana->nombre_plantilla}' (Campaña ID {$campana->id}) no encontrada en Meta. Se reintentará.");
                if ($campana->isDirty('whatsapp_template_id')) { // Guardar si solo se actualizó el ID de plantilla
                    $campana->save();
                }
            } elseif ($nuevoEstadoMeta) {
                $this->line("Plantilla '{$campana->nombre_plantilla}' (Campaña ID {$campana->id}) tiene estado en Meta: '{$nuevoEstadoMeta}'. Se mantiene PENDIENTE_APROBACION localmente hasta resolución final.");
                Log::info("Plantilla '{$campana->nombre_plantilla}' (Campaña ID {$campana->id}) con estado intermedio en Meta: '{$nuevoEstadoMeta}'.");
                if ($campana->isDirty('whatsapp_template_id')) { // Guardar si solo se actualizó el ID de plantilla
                    $campana->save();
                }
            } else {
                $this->error("Respuesta inesperada de la API para plantilla '{$campana->nombre_plantilla}' (Campaña ID {$campana->id}). Detalles: " . json_encode($resultadoApi));
                Log::error("Respuesta inesperada de API para plantilla '{$campana->nombre_plantilla}' (Campaña ID {$campana->id}).", $resultadoApi);
            }

            if ($campanasPendientes->count() > 1 && $fallidasVerificacion < 3) {
                sleep(1);
            }
        }

        $this->info("Proceso de actualización de estados de plantillas completado. Campañas actualizadas: {$actualizadas}. Verificaciones fallidas (API): {$fallidasVerificacion}.");
        Log::info("Tarea programada: Fin whatsapp:actualizar-estados-plantilla. Actualizadas: {$actualizadas}. Fallos API: {$fallidasVerificacion}.");
        return Command::SUCCESS;
    }
}
