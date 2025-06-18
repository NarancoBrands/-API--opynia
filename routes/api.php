<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CampanaController;
use App\Http\Controllers\ComercioController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\GoogleWebhookController;
use App\Http\Controllers\ResenasController;
use App\Http\Controllers\SolicitudController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/google/webhook', [GoogleWebhookController::class, 'handle'])->name('google.webhook');
Route::get('/google/auth/redirect', [GoogleAuthController::class, 'redirect'])->name('google.auth.redirect');
Route::post('/google/start-watching', [GoogleAuthController::class, 'startWatching']);
Route::post('/google/disconnect', [GoogleAuthController::class, 'disconnect']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('comercios')->group(function () {
        Route::get('/getComercios', [ComercioController::class, 'index']); // Obtener todos los comercios
        Route::post('/createComercio', [ComercioController::class, 'store']); // Crear comercio
        Route::get('/getComercio/{id}', [ComercioController::class, 'show']); // Obtener comercio por ID
        Route::put('/actualizarComercio/{id}', [ComercioController::class, 'update']); // Actualizar comercio
        Route::delete('/eliminarComercio/{id}', [ComercioController::class, 'destroy']); // Eliminar comercio


    });

// Grupo de rutas para campañas
    Route::prefix('campanas')->group(function () {
        Route::get('/getCampanas', [CampanaController::class, 'index']); // Obtener todas las campañas
        Route::post('/createCampana', [CampanaController::class, 'store']); // Crear campaña
        Route::get('/getCampana/{id}', [CampanaController::class, 'show']); // Obtener campaña por ID
        Route::post('/actualizarCampana/{id}', [CampanaController::class, 'update']); // Actualizar campaña
        Route::delete('/eliminarCampana/{id}', [CampanaController::class, 'destroy']); // Eliminar campaña
        Route::put('/{campana}/template-name', [CampanaController::class, 'updateTemplateName']);
    });

// Grupo de rutas para reseñas
    Route::prefix('resenas')->group(function () {
        // Route::get('/getResena', [ResenasController::class, 'index']); // Obtener todas las campañas
        Route::post('/createResenas', [ResenasController::class, 'addResena']); // Crear campaña
        // Route::get('/getResenas/{id}', [ResenasController::class, 'show']); // Obtener campaña por ID
        // Route::post('/actualizarResenas/{id}', [ResenasController::class, 'update']); // Actualizar campaña
        Route::delete('/eliminarResenas/{id}', [ResenasController::class, 'destroy']); // Eliminar campaña

        Route::get('/getByIdComercio/{id}', [ResenasController::class, 'getResenasByIdComercio']); // Crear campaña
        Route::get('/estadisticas', [ResenasController::class, 'getStats']);
        Route::patch('/{resena}/google-event', [ResenasController::class, 'updateGoogleEventId']);
    });
// Grupo de rutas para API de WhatsApp
    Route::prefix('whatsapp')->group(function () {
        Route::post('/messages', [WhatsAppController::class, 'enviar']);
        Route::post('/templates', [WhatsAppController::class, 'crearPlantilla']);
        Route::post('/media', [WhatsAppController::class, 'subirMedia']);
        Route::post('/templates/media-handles', [WhatsAppController::class, 'obtenerHandleParaPlantilla']);
        Route::get('/templates/{templateName}/status', [WhatsAppController::class, 'obtenerEstadoPlantilla']);
    });

    Route::prefix('solicitudes')->group(function () {
        Route::get('/getSolicitudes', [SolicitudController::class, 'index']);
        Route::post('/crear', [SolicitudController::class, 'store']);
        Route::delete('/eliminar/{solicitud}', [SolicitudController::class, 'destroy']);
    });
});