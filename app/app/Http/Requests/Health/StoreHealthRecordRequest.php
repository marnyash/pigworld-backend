<?php

namespace App\Http\Requests\Health;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHealthRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'animal_id' => ['required', 'exists:animals,id'],
            'type' => ['required', Rule::in(['vaccination', 'treatment', 'deworming', 'mortality'])],
            'status' => ['required', Rule::in(['healthy', 'recovering', 'critical', 'deceased'])],
            'rfid' => ['nullable', 'string', 'max:100'],
            'symptoms' => ['nullable', 'array'],
            'symptoms.*' => ['string', 'max:100'],
            'diagnosis' => ['nullable', 'string', 'max:1000'],
            'medication' => ['nullable', 'string', 'max:200'],
            'dosage' => ['nullable', 'string', 'max:200'],
            'veterinarian' => ['nullable', 'string', 'max:200'],
            'visit_date' => ['nullable', 'date_format:Y-m-d\TH:i:s\Z', 'before_or_equal:now'],
            'next_checkup_date' => ['nullable', 'date_format:Y-m-d\TH:i:s\Z'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment_urls' => ['nullable', 'array'],
            'attachment_urls.*' => ['url', 'max:500'],
        ];
    }
}
