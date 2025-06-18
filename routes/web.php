<?php

use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\GoogleWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ComercioController;

Route::get('/', function () {
    return view('welcome');
});

// routes/api.php
Route::post('comercios/login', [ComercioController::class, 'login']);

Route::get('/google/auth/callback', [GoogleAuthController::class, 'callback'])->name('google.auth.callback');
Route::get('/migrate-seed', function (Request $request) {

    $claveSecreta = env('MIGRATE_SECRET_KEY', 'MI_CLAVE_SECRETA');

    $claveUrl = $request->query('key');

    if ($claveUrl !== $claveSecreta) {
        abort(403, 'Acceso denegado');
    }

    try {
        Artisan::call('migrate:fresh', [
            '--force' => true
        ]);

        Artisan::call('db:seed', [
            '--force' => true
        ]);

        return 'Migraciones y seeders ejecutados correctamente.';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});
Route::get('/create-storage-link', function (Request $request) {
    $claveSecreta = env('MIGRATE_SECRET_KEY', 'MI_CLAVE_SECRETA');
    $claveUrl = $request->query('key');

    if ($claveUrl !== $claveSecreta) {
        abort(403, 'Acceso denegado');
    }

    try {
        Artisan::call('storage:link');
        return 'Symlink creado correctamente.';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});
