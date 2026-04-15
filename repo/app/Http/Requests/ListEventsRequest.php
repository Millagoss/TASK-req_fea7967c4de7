<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id'     => ['nullable', 'integer'],
            'event_type'  => ['nullable', 'in:browse,search,click,favorite,rate,comment'],
            'target_type' => ['nullable', 'string', 'max:50'],
            'target_id'   => ['nullable', 'integer'],
            'date_from'   => ['nullable', 'date'],
            'date_to'     => ['nullable', 'date'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_dir'    => ['nullable', 'in:asc,desc'],
        ];
    }
}
