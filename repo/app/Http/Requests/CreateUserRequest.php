<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username'     => ['required', 'string', 'max:100', 'unique:users,username'],
            'password'     => ['required', 'string', 'min:12'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'is_active'    => ['nullable', 'boolean'],
            'roles'        => ['nullable', 'array'],
            'roles.*'      => ['string', 'exists:roles,name'],
        ];
    }
}
