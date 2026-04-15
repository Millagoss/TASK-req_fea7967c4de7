<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title'  => ['required', 'string', 'min:1', 'max:200'],
            'artist' => ['required', 'string', 'min:1', 'max:200'],
        ];
    }
}
