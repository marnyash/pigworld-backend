<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventoryItemId' => $this->inventory_item_id,
            'type' => $this->type,
            'quantity' => (float) $this->quantity,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'recordedBy' => $this->recorded_by,
            'createdAt' => $this->created_at,
        ];
    }
}
