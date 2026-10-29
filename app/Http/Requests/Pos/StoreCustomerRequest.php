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
            'name'            => ['required', 'string', 'max:255'],
            'phone'           => [
                'required',
                'string',
                Rule::unique('pos_customers', 'phone')->where(function ($query) use ($branchId) {
                    return $query->where('branch_id', $branchId);
                })->ignore($customerId),
            ],
            'email'           => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('pos_customers', 'email')->where(function ($query) use ($branchId) {
                    return $query->where('branch_id', $branchId);
                })->ignore($customerId),
            ],
            'whatsapp_number' => ['nullable', 'string', 'max:50'],
            'same_as_phone'   => ['nullable', 'boolean'],
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

    public function messages(): array
    {
        return [
            'phone.unique' => 'A customer with this phone number already exists in this branch.',
            'email.unique' => 'A customer with this email address already exists in this branch.',
        ];
    }
}
