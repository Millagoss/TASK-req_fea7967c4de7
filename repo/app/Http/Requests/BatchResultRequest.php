<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'observations'                       => ['required', 'array'],
            'observations.*.code'                => ['required', 'string'],
            'observations.*.subject_identifier'  => ['required', 'string'],
            'observations.*.value'               => ['required'],
            'observations.*.unit'                => ['nullable', 'string'],
            'observations.*.observed_at'         => ['required', 'date'],
        ];
    }
}
