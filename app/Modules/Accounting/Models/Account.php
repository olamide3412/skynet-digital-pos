<?php

namespace App\Modules\Accounting\Models;

use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $table = 'accounts';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'code',
        'name',
        'type', // asset, liability, equity, revenue, expense
        'sub_type',
        'parent_id',
        'is_system',
        'is_active',
        'normal_balance', // debit, credit
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function isDebit(): bool
    {
        return strtolower($this->normal_balance) === 'debit';
    }

    public function isCredit(): bool
    {
        return strtolower($this->normal_balance) === 'credit';
    }
}
