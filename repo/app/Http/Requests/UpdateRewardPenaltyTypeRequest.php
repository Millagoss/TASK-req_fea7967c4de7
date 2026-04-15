<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRewardPenaltyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'                   => ['sometimes', 'string', 'max:200'],
            'category'               => ['sometimes', 'string', 'in:reward,penalty'],
            'severity'               => ['nullable', 'string', 'in:low,medium,high,critical'],
            'default_points'         => ['sometimes', 'integer'],
            'default_expiration_days' => ['nullable', 'integer', 'min:1'],
            'is_active'              => ['sometimes', 'boolean'],
        ];
    }
}
