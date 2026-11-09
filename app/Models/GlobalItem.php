<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalItem extends Model
{
    protected $fillable = [
        'item_name', 'barcode_number',
        'buy_price', 'price', 'wholesale_price',
        'unit_label',
        'item_description', 'image_path', 'category_hint', 'brand_hint',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'buy_price'              => 'decimal:2',
            'price'                  => 'decimal:2',
            'wholesale_price'        => 'decimal:2',
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    /**
     * Import this global item into the given branch.
     * Creates a new Item row owned by the branch.
     * The branch then fully owns its copy — no sync to global.
     */
    public function importToBranch(Branch $branch, ?int $categoryId = null, ?int $brandId = null): Item
    {
        return Item::create([
            'branch_id'              => $branch->id,
            'category_id'            => $categoryId,
            'brand_id'               => $brandId,
            'item_name'              => $this->item_name,
            'barcode_number'         => $this->barcode_number,
            'buy_price'              => $this->buy_price,
            'price'                  => $this->price,
            'wholesale_price'        => $this->wholesale_price,
            'unit_label'             => $this->unit_label,
            'item_description'       => $this->item_description,
            'image_path'             => $this->image_path,
            'back_store_qty'         => rand(30, 150),
            'front_store_qty'        => rand(15, 60),
        ]);
    }
}
