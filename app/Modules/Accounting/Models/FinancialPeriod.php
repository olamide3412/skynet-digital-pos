<?php

namespace App\Modules\Accounting\Models;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialPeriod extends Model
{
    use HasFactory;

    protected $table = 'financial_periods';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'name',
        'starts_on',
        'ends_on',
        'status', // open, closed
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on'   => 'date:Y-m-d',
        'closed_at' => 'datetime',
    ];

    protected $appends = [
        'start_date',
        'end_date',
    ];

    public function getStartDateAttribute(): ?string
    {
        return $this->starts_on ? $this->starts_on->format('Y-m-d') : null;
    }

    public function getEndDateAttribute(): ?string
    {
        return $this->ends_on ? $this->ends_on->format('Y-m-d') : null;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
