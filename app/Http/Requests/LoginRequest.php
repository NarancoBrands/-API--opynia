<?php

namespace App\Http\Requests;

use App\Models\Comercio;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

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
        // $this->ensureIsNotRateLimited();

        if (! $user = $this->login($this->only('email', 'password'))) {
            // RateLimiter::hit($this->throttleKey());

            throw new HttpResponseException(response()->json(
                [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => [
                        'email|password' => [
                            // 'The provided email or password is incorrect.',
                            'El email o contraseña son incorrectos.',
                        ],
                    ],
                ],
                Response::HTTP_FORBIDDEN
            ));
        }

        // RateLimiter::clear($this->throttleKey());

        return $user;
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

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip());
    }

    public function login($credentials)
    {
        $user = Comercio::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return false;
        }

        return $user;
    }

}
