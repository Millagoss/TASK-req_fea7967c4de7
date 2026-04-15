<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisciplinaryRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type_id'             => ['required', 'integer', 'exists:reward_penalty_types,id'],
            'subject_user_id'     => ['required', 'integer', 'exists:users,id'],
            'evaluation_cycle_id' => ['nullable', 'integer', 'exists:evaluation_cycles,id'],
            'leader_profile_id'   => ['nullable', 'integer', 'exists:leader_profiles,id'],
            'reason'              => ['required', 'string', 'max:2000'],
            'points'              => ['nullable', 'integer'],
        ];
    }
}
