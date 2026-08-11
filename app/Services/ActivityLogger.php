<?php

namespace App\Services;

use App\Models\PosLog;
use Throwable;

class ActivityLogger
{
    public static function log(
        string $actionType,
        string $message,
        ?int $branchId = null,
        ?array $details = null,
        ?int $userId = null
    ): ?PosLog {
        try {
            $user = auth()->user();
            $req  = request();

            $resolvedBranchId = $branchId 
                ?? (function_exists('current_branch') ? current_branch()?->id : null)
                ?? $user?->branch_id;

            return PosLog::create([
                'branch_id'   => $resolvedBranchId,
                'user_id'     => $userId ?? $user?->id,
                'log'         => $message,
                'action_type' => $actionType,
                'ip_address'  => $req?->ip(),
                'user_agent'  => $req ? substr($req->userAgent() ?? '', 0, 255) : null,
                'details'     => $details,
                'created_at'  => now(),
            ]);
        } catch (Throwable $e) {
            logger()->error('ActivityLogger exception: ' . $e->getMessage());
            return null;
        }
    }

    public static function sale(string $message, ?int $branchId = null, ?array $details = null): ?PosLog
    {
        return self::log('sale', $message, $branchId, $details);
    }

    public static function stock(string $message, ?int $branchId = null, ?array $details = null): ?PosLog
    {
        return self::log('stock', $message, $branchId, $details);
    }

    public static function return(string $message, ?int $branchId = null, ?array $details = null): ?PosLog
    {
        return self::log('return', $message, $branchId, $details);
    }

    public static function auth(string $message, ?int $branchId = null, ?array $details = null, ?int $userId = null): ?PosLog
    {
        return self::log('auth', $message, $branchId, $details, $userId);
    }

    public static function item(string $message, ?int $branchId = null, ?array $details = null): ?PosLog
    {
        return self::log('item', $message, $branchId, $details);
    }

    public static function userAction(string $message, ?int $branchId = null, ?array $details = null): ?PosLog
    {
        return self::log('user', $message, $branchId, $details);
    }

    public static function setting(string $message, ?array $details = null): ?PosLog
    {
        return self::log('setting', $message, null, $details);
    }
}
