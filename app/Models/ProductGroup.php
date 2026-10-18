<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'created_by',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function groupItems(): HasMany
    {
        return $this->hasMany(ProductGroupItem::class);
    }

    public function items()
    {
        return $this->belongsToMany(Item::class, 'product_group_items', 'product_group_id', 'item_id')
            ->withPivot(['branch_id', 'link_source', 'linked_by'])
            ->withTimestamps();
    }
}
