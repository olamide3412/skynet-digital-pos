<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $itemId = $this->route('item')?->id;

        return [
            'category_id'      => ['required', 'integer', 'exists:categories,id'],
            'group_address_id' => ['nullable', 'integer', 'exists:group_addresses,id'],
            'item_name'        => ['required', 'string', 'max:255'],
            'barcode_number'   => ['required', 'string', Rule::unique('items','barcode_number')->ignore($itemId)],
            'qty'              => ['required', 'integer', 'min:0'],
            'buy_price'        => ['required', 'numeric', 'min:0'],
            'price'            => ['required', 'numeric', 'min:0'],
            'wholesale_price'  => ['required', 'numeric', 'min:0'],
            'expiry_date'      => ['nullable', 'date'],
            'item_description' => ['nullable', 'string', 'max:255'],
            'price_locked'     => ['nullable', 'boolean'],
            'image'            => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ];
    }
}
