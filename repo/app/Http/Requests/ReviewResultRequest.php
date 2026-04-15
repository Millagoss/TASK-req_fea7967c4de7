<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'decision'       => ['required', 'string', 'in:approved,rejected'],
            'review_comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
