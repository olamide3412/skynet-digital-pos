<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $customerId = $this->route('customer')?->id;

        return [
            'name'            => ['required', 'string', 'max:255'],
            'phone'           => ['required', 'string', Rule::unique('pos_customers','phone')->ignore($customerId)],
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
