<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AdminBroadcast;
use App\Models\Branch;
use App\Models\Role;
use App\Services\BroadcastService;
use App\Services\MailConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AdminBroadcast::with('sender:id,name,email');

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $broadcasts = $query->latest()->paginate(15)->withQueryString();

        $stats = [
            'total_broadcasts' => AdminBroadcast::count(),
            'in_app_delivered' => (int) AdminBroadcast::sum('in_app_count'),
            'emails_sent'      => (int) AdminBroadcast::sum('email_sent_count'),
            'emails_failed'    => (int) AdminBroadcast::sum('email_failed_count'),
        ];

        $branches = Branch::where('is_active', true)
            ->withCount('users')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'address']);

        $roles = [
            ['key' => 'branch-admin', 'name' => 'Branch Admins / Managers'],
            ['key' => 'cashier',      'name' => 'Cashiers / Sales Reps'],
            ['key' => 'store-keeper',  'name' => 'Store Keepers / Inventory Staff'],
        ];

        $mailConfig = [
            'is_enabled'    => MailConfigService::isConfiguredAndEnabled(),
            'settings'      => MailConfigService::getSettings(),
        ];

        return Inertia::render('SuperAdmin/Broadcasts/Index', [
            'broadcasts' => $broadcasts,
            'stats'      => $stats,
            'branches'   => $branches,
            'roles'      => $roles,
            'mailConfig' => $mailConfig,
            'filters'    => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:200',
            'message'     => 'required|string|max:10000',
            'target_type' => 'required|in:all,branch,branches,role,branch_role',
            'target_data' => 'nullable|array',
            'channels'    => 'required|array|min:1',
            'channels.*'  => 'in:in_app,email',
        ]);

        $broadcast = BroadcastService::sendBroadcast($data, Auth::user());

        $summary = "Broadcast #{$broadcast->id} dispatched: {$broadcast->in_app_count} in-app notifications";
        if (in_array('email', $data['channels'])) {
            $summary .= ", {$broadcast->email_sent_count} emails sent";
        }

        return back()->with('success', $summary);
    }

    public function show(AdminBroadcast $broadcast): JsonResponse
    {
        $broadcast->load('sender:id,name,email');

        return response()->json([
            'broadcast' => $broadcast,
        ]);
    }
}
