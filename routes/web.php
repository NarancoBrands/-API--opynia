<?php

use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\GoogleWebhookController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ComercioController;

Route::get('/', function () {
    return view('welcome');
});

// routes/api.php
Route::post('comercios/login', [ComercioController::class, 'login']);

Route::get('/google/auth/callback', [GoogleAuthController::class, 'callback'])->name('google.auth.callback');
