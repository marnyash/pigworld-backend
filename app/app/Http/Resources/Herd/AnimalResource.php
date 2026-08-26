<?php

namespace App\Http\Resources\Herd;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Animal */
class AnimalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'farm_id' => (string) $this->farm_id,
            'tag' => $this->tag,
            'type' => $this->type,
            'sex' => $this->sex,
            'status' => $this->status,
            'birth_date' => $this->birth_date?->toDateString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
