<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManagePlaylistSongsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'song_id' => ['required', 'integer', 'exists:songs,id'],
            'position' => ['required', 'integer', 'min:1'],
        ];
    }
}
