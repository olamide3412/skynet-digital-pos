<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminBroadcast extends Model
{
    protected $table = 'admin_broadcasts';

    protected $fillable = [
        'sender_id',
        'title',
        'message',
        'target_type',
        'target_data',
        'channels',
        'in_app_count',
        'email_sent_count',
        'email_failed_count',
        'no_email_count',
        'delivery_logs',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'target_data'        => 'array',
            'channels'           => 'array',
            'delivery_logs'      => 'array',
            'in_app_count'       => 'integer',
            'email_sent_count'   => 'integer',
            'email_failed_count' => 'integer',
            'no_email_count'     => 'integer',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class, 'reference_id')
            ->where('reference_type', self::class);
    }
}
