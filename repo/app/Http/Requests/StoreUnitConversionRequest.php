<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'measurement_code_id' => ['required', 'integer', 'exists:measurement_codes,id'],
            'from_unit'           => ['required', 'string', 'max:50'],
            'to_unit'             => ['required', 'string', 'max:50'],
            'factor'              => ['required', 'numeric'],
            'offset'              => ['nullable', 'numeric'],
        ];
    }
}
