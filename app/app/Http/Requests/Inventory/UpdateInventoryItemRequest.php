<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|in:Feed,Medicine,Vaccines,Equipment,Cleaning,RFID,Other',
            'sku' => 'sometimes|string|unique:inventory_items,sku,' . $this->route('item')->id,
            'barcode' => 'sometimes|nullable|string|unique:inventory_items,barcode,' . $this->route('item')->id,
            'quantity' => 'sometimes|numeric|min:0',
            'unit' => 'sometimes|string|max:50',
            'minimum_level' => 'sometimes|numeric|min:0',
            'cost_price' => 'sometimes|numeric|min:0',
            'supplier' => 'sometimes|nullable|string|max:255',
            'expiry_date' => 'sometimes|nullable|date_format:Y-m-d|after:today',
            'storage_location' => 'sometimes|nullable|string|max:255',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
