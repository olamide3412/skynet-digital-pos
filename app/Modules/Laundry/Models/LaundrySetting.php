<?php

namespace App\Modules\Laundry\Models;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaundrySetting extends Model
{
    protected $table = 'laundry_settings';

    protected $fillable = [
        'branch_id',
        'order_prefix',
        'default_turnaround_days',
        'unclaimed_after_days',
        'whatsapp_template',
        'default_country_code',
        'receipt_footer',
        'allow_price_override',
        'allow_unpaid_pickup',
    ];

    protected function casts(): array
    {
        return [
            'default_turnaround_days' => 'integer',
            'unclaimed_after_days'    => 'integer',
            'allow_price_override'    => 'boolean',
            'allow_unpaid_pickup'     => 'boolean',
        ];
    }

    protected $appends = [
        'default_turnaround_hours',
    ];

    public function getDefaultTurnaroundHoursAttribute(): int
    {
        return ($this->default_turnaround_days ?? 2) * 24;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get or create settings for a given branch.
     */
     public static function forBranch(int $branchId): static
     {
         return static::firstOrCreate(
             ['branch_id' => $branchId],
             [
                 'order_prefix'            => 'LDY-',
                 'default_turnaround_days' => 2,
                 'unclaimed_after_days'    => 14,
                 'whatsapp_template'       => "Hello {customer_name}, your laundry order #{order_number} is ready for pickup at {branch_name}! Total due: {balance}. Thank you for choosing us.",
                 'default_country_code'    => '+234',
                 'receipt_footer'          => "Please present this ticket upon garment collection. Items unclaimed after 30 days are subject to disposal.",
                 'allow_price_override'    => false,
                 'allow_unpaid_pickup'     => false,
             ]
         );
     }

    /**
     * Alias for forBranch.
     */
    public static function getForBranch(int $branchId): static
    {
        return static::forBranch($branchId);
    }
}
