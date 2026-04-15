<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaderProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title'      => ['sometimes', 'string', 'max:200'],
            'department' => ['sometimes', 'string', 'max:200'],
            'campus'     => ['nullable', 'string', 'max:200'],
        ];
    }
}
