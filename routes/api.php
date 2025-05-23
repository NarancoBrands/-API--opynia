<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ComercioController;
use App\Http\Controllers\CampanaController;
use App\Http\Controllers\ResenasController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::prefix('comercios')->group(function () {
    Route::get('/getComercios', [ComercioController::class, 'index']); // Obtener todos los comercios
    Route::post('/createComercio', [ComercioController::class, 'store']); // Crear comercio
    Route::get('/getComercio/{id}', [ComercioController::class, 'show']); // Obtener comercio por ID
    Route::put('/actualizarComercio/{id}', [ComercioController::class, 'update']); // Actualizar comercio
    Route::delete('/eliminarComercio/{id}', [ComercioController::class, 'destroy']); // Eliminar comercio
    Route::post('/login', [AuthController::class, 'login']); // Login de comercio en calendario

});

// Grupo de rutas para campañas
Route::prefix('campanas')->group(function () {
    Route::get('/getCampanas', [CampanaController::class, 'index']); // Obtener todas las campañas
    Route::post('/createCampana', [CampanaController::class, 'store']); // Crear campaña
    Route::get('/getCampana/{id}', [CampanaController::class, 'show']); // Obtener campaña por ID
    Route::post('/actualizarCampana/{id}', [CampanaController::class, 'update']); // Actualizar campaña
    Route::delete('/eliminarCampana/{id}', [CampanaController::class, 'destroy']); // Eliminar campaña
});

// Grupo de rutas para reseñas
Route::prefix('resenas')->group(function () {
    // Route::get('/getResena', [ResenasController::class, 'index']); // Obtener todas las campañas
    Route::post('/createResenas', [ResenasController::class, 'addResena']); // Crear campaña
    // Route::get('/getResenas/{id}', [ResenasController::class, 'show']); // Obtener campaña por ID
    // Route::post('/actualizarResenas/{id}', [ResenasController::class, 'update']); // Actualizar campaña
    // Route::delete('/eliminarResenas/{id}', [ResenasController::class, 'destroy']); // Eliminar campaña

    Route::get('/getByIdComercio/{id}', [ResenasController::class, 'getResenasByIdComercio']); // Crear campaña

});
