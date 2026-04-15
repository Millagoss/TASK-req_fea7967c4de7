<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeasurementCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'code'                 => ['required', 'string', 'max:50', 'unique:measurement_codes,code'],
            'display_name'         => ['required', 'string', 'max:200'],
            'unit'                 => ['required', 'string', 'max:50'],
            'value_type'           => ['required', 'string', 'in:numeric,text,coded'],
            'reference_range_low'  => ['nullable', 'numeric'],
            'reference_range_high' => ['nullable', 'numeric'],
            'is_active'            => ['boolean'],
        ];
    }
}
