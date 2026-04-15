<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeasurementCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'code'                 => ['sometimes', 'string', 'max:50', 'unique:measurement_codes,code,' . $id],
            'display_name'         => ['sometimes', 'string', 'max:200'],
            'unit'                 => ['sometimes', 'string', 'max:50'],
            'value_type'           => ['sometimes', 'string', 'in:numeric,text,coded'],
            'reference_range_low'  => ['nullable', 'numeric'],
            'reference_range_high' => ['nullable', 'numeric'],
            'is_active'            => ['boolean'],
        ];
    }
}
