<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.item_id'          => ['required', 'integer', 'exists:items,id'],
            'items.*.qty'              => ['required', 'integer', 'min:1'],
            'items.*.price'            => ['required', 'numeric', 'min:0'],
            'payment_method'           => ['required', 'string'],
            'amount_paid'              => ['required', 'numeric', 'min:0'],
            'purchase_type'            => ['nullable', 'in:Wholesale,Consumer'],
            'customer_id'              => ['nullable', 'integer', 'exists:pos_customers,id'],
            'consultation_fee'         => ['nullable', 'numeric', 'min:0'],
            'discount_amount'          => ['nullable', 'numeric', 'min:0'],
            'sale_discount_id'         => ['nullable', 'integer', 'exists:sale_discounts,id'],
            'bank_transfer'            => ['nullable', 'numeric', 'min:0'],
            'cash'                     => ['nullable', 'numeric', 'min:0'],
            'is_debt'                  => ['nullable', 'boolean'],
        ];
    }
}
