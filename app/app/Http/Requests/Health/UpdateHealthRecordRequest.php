<?php

namespace App\Http\Requests\Health;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHealthRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(['vaccination', 'treatment', 'deworming', 'mortality'])],
            'status' => ['sometimes', Rule::in(['healthy', 'recovering', 'critical', 'deceased'])],
            'rfid' => ['nullable', 'string', 'max:100'],
            'symptoms' => ['nullable', 'array'],
            'symptoms.*' => ['string', 'max:100'],
            'diagnosis' => ['nullable', 'string', 'max:1000'],
            'medication' => ['nullable', 'string', 'max:200'],
            'dosage' => ['nullable', 'string', 'max:200'],
            'veterinarian' => ['nullable', 'string', 'max:200'],
            'visit_date' => ['nullable', 'date_format:Y-m-d\TH:i:s\Z'],
            'next_checkup_date' => ['nullable', 'date_format:Y-m-d\TH:i:s\Z'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment_urls' => ['nullable', 'array'],
            'attachment_urls.*' => ['url', 'max:500'],
        ];
    }
}
