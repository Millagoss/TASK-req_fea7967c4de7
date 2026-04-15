<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $templateId = $this->route('id');

        return [
            'name'        => ['sometimes', 'string', 'max:100', Rule::unique('notification_templates', 'name')->ignore($templateId)],
            'subject'     => ['sometimes', 'string', 'max:255'],
            'body'        => ['sometimes', 'string'],
            'variables'   => ['sometimes', 'array'],
            'variables.*' => ['string'],
        ];
    }
}
