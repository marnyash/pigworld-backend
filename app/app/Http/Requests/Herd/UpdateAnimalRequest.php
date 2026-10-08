<?php

namespace App\Http\Requests\Herd;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnimalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tag' => ['sometimes', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(['active', 'sold', 'deceased'])],
            'birth_date' => ['nullable', 'date'],
            'weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
