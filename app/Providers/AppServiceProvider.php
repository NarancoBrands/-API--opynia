<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Application;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        Event::listen([

        ], [
            \App\Listeners\ActualizarTotalResenas::class,
        ]);
    }

    public function boot()
    {

    }
}
