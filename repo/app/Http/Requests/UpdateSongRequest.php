<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title'           => ['nullable', 'string', 'min:1', 'max:200'],
            'artist'          => ['nullable', 'string', 'min:1', 'max:200'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:7200'],
            'audio_quality'   => ['nullable', 'in:MP3_320,FLAC_16_44,FLAC_24_96'],
            'tags'            => ['nullable', 'array', 'max:20'],
            'tags.*'          => ['string', 'regex:/^[a-z0-9-]+$/', 'min:2', 'max:24'],
        ];
    }
}
