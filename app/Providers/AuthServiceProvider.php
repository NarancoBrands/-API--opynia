<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport; // Importamos Passport

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Aquí registramos los scopes para que Passport los reconozca
        Passport::tokensCan([
            'gestor' => 'Permisos de administrador del sistema',
            'comercio' => 'Permisos de usuario de un comercio',
        ]);
    }
}