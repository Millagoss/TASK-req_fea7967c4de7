<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEvaluationCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'       => ['sometimes', 'required', 'string', 'max:200'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date'   => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
        ];
    }
}
