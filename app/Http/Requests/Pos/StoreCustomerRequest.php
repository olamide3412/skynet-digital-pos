<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $customer = $this->route('customer');
        $customerId = is_object($customer) ? $customer->id : $customer;

        return [
            'name'            => ['required', 'string', 'max:255'],
            'phone'           => ['required', 'string', Rule::unique('pos_customers', 'phone')->where(function ($query) {
                return $query->where('branch_id', current_branch()?->id);
            })->ignore($customerId)],
            'address'         => ['nullable', 'string'],
            'gender'          => ['nullable', 'string', 'in:Male,Female,Other'],
            'dob'             => ['nullable', 'date'],
            'note'            => ['nullable', 'string'],
            'contact_name'    => ['nullable', 'string'],
            'contact_phone'   => ['nullable', 'string'],
            'contact_address' => ['nullable', 'string'],
            'watch_list'      => ['nullable', 'boolean'],
        ];
    }
}
