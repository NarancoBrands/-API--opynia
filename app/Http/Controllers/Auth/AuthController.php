<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;


class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        // return $request;
        //Respuesta con el usuario logeado y su token de acceso
        $response = [];

        $user = $request->authenticate();
        $token = $user->createToken(env('PASSWORD_TOKEN'))->accessToken;
        $user->google_connected = !is_null($user->google_refresh_token);
        $response = [
            'usuario'    => $user,
            'token_type' => 'Bearer',
            'token'      => $token,
        ];

        return $this->sendResponse($response, 'Comercio logueado correctamente.');
    }
}
