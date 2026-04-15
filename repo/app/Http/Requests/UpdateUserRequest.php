<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:255'],
            'password'     => ['nullable', 'string', 'min:12'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }
}
