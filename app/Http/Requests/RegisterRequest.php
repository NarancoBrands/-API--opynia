<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'nombre'    => 'required',
            'email'     => 'required|email:rfc,filter,spoof|unique:users,email',
            'password'  => 'required|string',
            'password_confirmation' => 'required|same:password',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El campo nombre es obligatorio.',
            'email.required' => 'El campo email es obligatorio.',
            'email.email' => 'El email debe ser un email válido.',
            'email.unique' => 'El email ya está en uso.',
            'password.required' => 'El campo contraseña es obligatorio.',
            'password_confirmation.required' => 'El campo confirmación de contraseña es obligatorio.',
            'password_confirmation.same' => 'Las contraseñas deben coincidir.',
        ];
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
}
