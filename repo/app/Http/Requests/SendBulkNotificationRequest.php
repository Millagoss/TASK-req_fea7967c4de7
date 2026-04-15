<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendBulkNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'template_id'     => ['required', 'integer', 'exists:notification_templates,id'],
            'recipient_ids'   => ['required', 'array', 'min:1', 'max:10000'],
            'recipient_ids.*' => ['integer', 'exists:users,id'],
            'variables'       => ['required', 'array'],
        ];
    }
}
