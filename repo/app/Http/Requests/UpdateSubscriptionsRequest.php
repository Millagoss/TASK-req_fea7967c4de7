<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'subscriptions'                => ['required', 'array'],
            'subscriptions.*.template_id'  => ['required', 'integer', 'exists:notification_templates,id'],
            'subscriptions.*.is_subscribed' => ['required', 'boolean'],
        ];
    }
}
