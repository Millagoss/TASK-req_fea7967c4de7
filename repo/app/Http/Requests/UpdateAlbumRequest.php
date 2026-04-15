<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title'  => ['nullable', 'string', 'min:1', 'max:200'],
            'artist' => ['nullable', 'string', 'min:1', 'max:200'],
        ];
    }
}
