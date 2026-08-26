<?php

namespace App\Http\Requests\Herd;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnimalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tag' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::in(['sow'])],
            'sex' => ['required', Rule::in(['female'])],
            'status' => ['sometimes', Rule::in(['active', 'sold', 'deceased'])],
            'birth_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
