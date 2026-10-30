<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('items') && is_array($this->items)) {
            $items = $this->items;
            foreach ($items as &$item) {
                if (isset($item['unit_used'])) {
                    $item['unit_used'] = trim((string) $item['unit_used']);
                }
            }
            $this->merge(['items' => $items]);
        }
    }

    public function rules(): array
    {
        $branch = current_branch();
        if (!$branch) {
            $branchParam = $this->route('branch');
            if ($branchParam instanceof \App\Models\Branch) {
                $branch = $branchParam;
            } elseif ($branchParam) {
                $branch = \App\Models\Branch::where('slug', (string) $branchParam)->first();
            }
        }
        if (!$branch) {
            $branch = \Illuminate\Support\Facades\Auth::guard('web')->user()?->branch;
        }
        $branchId = $branch?->id;

        return [
            'items'                         => ['required', 'array', 'min:1'],
            'items.*.item_id'               => ['required', 'integer', 'exists:items,id'],
            'items.*.qty'                   => ['required', 'integer', 'min:1'],
            'items.*.price'                 => ['required', 'numeric', 'min:0'],
            'items.*.unit_used'             => ['nullable', 'string', 'max:100'],
            'items.*.imei_or_device_id'     => ['nullable', 'string', 'max:100'],
            'payment_method'                => ['required', 'string'],
            'amount_paid'                   => ['required', 'numeric', 'min:0'],
            'purchase_type'                 => ['nullable', 'in:Wholesale,Consumer'],
            'customer_id'                   => [
                'nullable',
                'integer',
                $branchId
                    ? Rule::exists('pos_customers', 'id')->where('branch_id', $branchId)
                    : 'exists:pos_customers,id',
            ],
            'consultation_fee'              => ['nullable', 'numeric', 'min:0'],
            'discount_amount'               => ['nullable', 'numeric', 'min:0'],
            'sale_discount_id'              => ['nullable', 'integer', 'exists:sale_discounts,id'],
            'bank_transfer'                 => ['nullable', 'numeric', 'min:0'],
            'cash'                          => ['nullable', 'numeric', 'min:0'],
            'is_debt'                       => ['nullable', 'boolean'],
        ];
    }
}
