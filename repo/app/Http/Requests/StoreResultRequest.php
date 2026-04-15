<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'code'               => ['required', 'string'],
            'subject_identifier' => ['required', 'string'],
            'value'              => ['required'],
            'unit'               => ['nullable', 'string'],
            'observed_at'        => ['required', 'date'],
        ];
    }
}
