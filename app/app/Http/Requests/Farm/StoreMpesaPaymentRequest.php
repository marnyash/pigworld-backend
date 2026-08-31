<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class StoreMpesaPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'farmOwner';
    }

    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', 'exists:subscription_plans,code'],
        ];
    }
}