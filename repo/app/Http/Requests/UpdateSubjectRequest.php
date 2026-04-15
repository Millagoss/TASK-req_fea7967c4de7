<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'identifier' => ['sometimes', 'string', 'max:100', 'unique:subjects,identifier,' . $id],
            'name'       => ['sometimes', 'string', 'max:200'],
            'metadata'   => ['nullable', 'array'],
            'campus'     => ['nullable', 'string', 'max:200'],
        ];
    }
}
