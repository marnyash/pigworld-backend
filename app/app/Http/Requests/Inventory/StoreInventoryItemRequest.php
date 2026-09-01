<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category' => 'required|in:Feed,Medicine,Vaccines,Equipment,Cleaning,RFID,Other',
            'sku' => 'required|string|unique:inventory_items,sku',
            'barcode' => 'nullable|string|unique:inventory_items,barcode',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'minimum_level' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'expiry_date' => 'nullable|date_format:Y-m-d|after:today',
            'storage_location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
