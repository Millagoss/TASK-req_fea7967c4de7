<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100', 'unique:notification_templates,name'],
            'subject'     => ['required', 'string', 'max:255'],
            'body'        => ['required', 'string'],
            'variables'   => ['required', 'array'],
            'variables.*' => ['string'],
        ];
    }
}
