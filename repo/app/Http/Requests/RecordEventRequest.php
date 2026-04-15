<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'event_type'  => ['required', 'in:browse,search,click,favorite,rate,comment'],
            'target_type' => ['required', 'string', 'max:50'],
            'target_id'   => ['required', 'integer'],
            'payload'     => ['nullable', 'array'],
        ];
    }
}
