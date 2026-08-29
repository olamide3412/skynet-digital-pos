<?php

namespace App\Modules\AI\Models;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiConversationLog extends Model
{
    protected $table = 'ai_conversation_logs';

    protected $fillable = [
        'branch_id',
        'user_id',
        'session_id',
        'role',
        'message',
        'detected_intent',
        'structured_payload',
        'provider_used',
        'model_used',
        'tokens_used',
        'execution_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'structured_payload' => 'array',
            'tokens_used'        => 'integer',
            'execution_time_ms'  => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
