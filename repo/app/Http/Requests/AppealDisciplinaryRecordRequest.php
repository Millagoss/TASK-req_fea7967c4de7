<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppealDisciplinaryRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'appeal_reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
