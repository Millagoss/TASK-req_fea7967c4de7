<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:100', 'unique:subjects,identifier'],
            'name'       => ['required', 'string', 'max:200'],
            'metadata'   => ['nullable', 'array'],
            'campus'     => ['nullable', 'string', 'max:200'],
        ];
    }
}
