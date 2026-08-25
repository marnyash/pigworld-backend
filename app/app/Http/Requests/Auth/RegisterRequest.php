<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', 'in:farmOwner,farmManager,farmWorker'],
            // Farm owners name the farm they are creating; managers/workers join an existing farm via its invite code.
            'farm_name' => ['required_if:role,farmOwner', 'string', 'max:255'],
            'invite_code' => ['required_unless:role,farmOwner', 'string', 'exists:farms,invite_code'],
            'mother_pig_count' => ['nullable', 'integer', 'min:0'],
            'piglet_groups' => ['nullable', 'array'],
            'piglet_groups.*.count' => ['required', 'integer', 'min:0'],
            'piglet_groups.*.age_months' => ['required', 'integer', 'min:0', 'max:120'],
            'pregnant_pig_count' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
