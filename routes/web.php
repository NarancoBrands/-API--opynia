<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ComercioController;

Route::get('/', function () {
    return view('welcome');
});

// routes/api.php
Route::post('comercios/login', [ComercioController::class, 'login']);

