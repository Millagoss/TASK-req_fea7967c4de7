<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCoverArtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'cover_art' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'cover_art.mimes' => 'Cover art must be a JPEG, PNG, or WebP image.',
            'cover_art.max' => 'Cover art file must not exceed 5MB.',
        ];
    }
}
