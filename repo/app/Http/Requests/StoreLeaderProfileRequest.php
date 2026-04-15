<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaderProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id'    => ['required', 'integer', 'exists:users,id', 'unique:leader_profiles,user_id'],
            'title'      => ['required', 'string', 'max:200'],
            'department' => ['required', 'string', 'max:200'],
            'campus'     => ['nullable', 'string', 'max:200'],
        ];
    }
}
