<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;


class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $response = [];

        $user = $request->authenticate();

        $scope = $user->token_scope;

        $token = $user->createToken(env('PASSWORD_TOKEN'), [$scope])->accessToken;

        $user->google_connected = !is_null($user->google_refresh_token);
        $response = [
            'usuario'    => $user,
            'scope'      => $scope,
            'token_type' => 'Bearer',
            'token'      => $token,
        ];

        return $this->sendResponse($response, 'Usuario logueado correctamente.');
    }
}
