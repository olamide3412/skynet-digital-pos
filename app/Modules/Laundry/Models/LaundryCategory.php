<?php

namespace App\Modules\Laundry\Models;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LaundryCategory extends Model
{
    protected $table = 'laundry_categories';

    protected $fillable = [
        'branch_id',
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($cat) {
            if (empty($cat->slug)) {
                $cat->slug = Str::slug($cat->name);
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(LaundryService::class, 'category_id')->orderBy('sort_order')->orderBy('name');
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }
}
