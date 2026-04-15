<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClearDisciplinaryRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'cleared_reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
