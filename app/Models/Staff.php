<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Staff extends Model
{
    protected $fillable = [
        'staff_number', 'firstname', 'surname', 'marital_status', 'gender',
        'dob', 'phone_number', 'state_of_origin', 'lga', 'present_qualification',
        'next_of_kin', 'phone_of_next_kin', 'address_of_next_kin',
        'residential_address', 'department', 'staff_position', 'monthly_salary',
        'bank_account', 'bank_name', 'date_of_appointment', 'photo_path', 'status',
    ];

    protected function casts(): array
    {
        return [
            'dob'                 => 'date',
            'date_of_appointment' => 'date',
            'monthly_salary'      => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id', 'staff_id');
    }
}
