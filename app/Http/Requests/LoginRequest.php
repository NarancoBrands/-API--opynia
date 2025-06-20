<?php

namespace App\Http\Requests;

use App\Models\Comercio;
use App\Models\Gestor;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Auth\AuthenticationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email'     => 'required|email:rfc,filter,spoof',
            'password'  => 'required|string',
        ];
    }

    public function authenticate()
    {
        // 1. Asegurarse de que la petición no esté bloqueada por "Rate Limiting"
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password');

        // 2. Intentar con el modelo Gestor
        $gestor = Gestor::where('email', $credentials['email'])->first();

        if ($gestor && Hash::check($credentials['password'], $gestor->password)) {
            RateLimiter::clear($this->throttleKey()); // Limpiar intentos si el login es exitoso
            return $gestor;
        }

        // 3. Si no es un gestor, intentar con el modelo Comercio
        $comercio = Comercio::where('email', $credentials['email'])->first();

        if ($comercio && Hash::check($credentials['password'], $comercio->password)) {
            RateLimiter::clear($this->throttleKey()); // Limpiar intentos si el login es exitoso
            return $comercio;
        }

        // 4. Si la autenticación falla, registrar un intento fallido
        RateLimiter::hit($this->throttleKey());

        // 5. Lanzar la excepción de autenticación estándar
        throw new AuthenticationException('Las credenciales proporcionadas son incorrectas.');
    }
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function validationFailed(): void
    {
        throw new HttpResponseException(response()->json(
            [
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $this->validator->errors(),
            ],
            Response::HTTP_BAD_REQUEST
        ));
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */


    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip());
    }




}
