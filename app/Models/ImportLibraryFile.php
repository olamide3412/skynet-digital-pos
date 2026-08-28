<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLibraryFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'item_type_id',
        'item_type_tag',
        'file_path',
        'file_name',
        'file_format',
        'file_size',
        'row_count',
        'user_id',
        'status',
        'description',
        'import_count',
        'last_imported_at',
    ];

    protected $casts = [
        'file_size'        => 'integer',
        'row_count'        => 'integer',
        'import_count'     => 'integer',
        'last_imported_at' => 'datetime',
    ];

    protected $appends = [
        'file_size_formatted',
        'is_archived',
    ];

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ImportItemType::class, 'item_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    public function getIsArchivedAttribute(): bool
    {
        return $this->status === 'archived';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
