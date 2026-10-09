<?php

namespace App\Modules\Accounting\Models;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory;

    protected $table = 'journal_entries';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'entry_number',
        'entry_date',
        'description',
        'source_type',
        'source_id',
        'event',
        'status', // posted, reversed, draft
        'reversal_of_id',
        'financial_period_id',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    protected $appends = [
        'reference',
    ];

    public function getReferenceAttribute(): ?string
    {
        if (preg_match('/\(Ref:\s*([^)]+)\)/', $this->description ?? '', $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of_id');
    }

    public function reversedBy(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(JournalEntry::class, 'reversal_of_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function getTotalDebitAttribute(): float
    {
        return $this->relationLoaded('lines')
            ? (float) $this->lines->sum('debit')
            : (float) $this->lines()->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return $this->relationLoaded('lines')
            ? (float) $this->lines->sum('credit')
            : (float) $this->lines()->sum('credit');
    }

    public function totalDebit(): float
    {
        return $this->total_debit;
    }

    public function totalCredit(): float
    {
        return $this->total_credit;
    }

    public function isBalanced(): bool
    {
        return round($this->totalDebit(), 2) === round($this->totalCredit(), 2);
    }
}
