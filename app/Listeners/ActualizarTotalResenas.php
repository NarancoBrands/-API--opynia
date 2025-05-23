<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class ActualizarTotalResenas implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels, Dispatchable;

    public function handle($event)
    {
        $comercio = $event->reseña->comercio;

        if ($comercio) {
            $comercio->totalResenas++;
            $comercio->save();
        }
    }
}
