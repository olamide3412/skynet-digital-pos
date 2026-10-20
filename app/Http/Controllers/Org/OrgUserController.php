<?php

namespace App\Http\Controllers\Org;

use App\Actions\User\DisableUserAction;
use App\Actions\User\EnableUserAction;
use App\Actions\User\MoveUserToBranchAction;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Policies\OrgUserPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class OrgUserController extends Controller
{
    public function __construct(
        protected OrgUserPolicy $policy,
        protected MoveUserToBranchAction $moveAction,
        protected DisableUserAction $disableAction,
        protected EnableUserAction $enableAction
    ) {}

    /**
     * Resolve the active organization for the current user session.
     */
    protected function resolveOrganization(Request $request): ?Organization
    {
        $user = Auth::guard('web')->user();

        if ($user->isSuperAdmin()) {
            if ($request->has('org_id')) {
                return Organization::find($request->get('org_id')) ?: Organization::first();
            }
            return $user->organization_id ? Organization::find($user->organization_id) : Organization::first();
        }

        return $user->organization_id ? Organization::find($user->organization_id) : null;
    }

    /**
     * Display the paginated staff user list across all branches of the organization.
     */
    public function index(Request $request): Response
    {
        $actor = Auth::guard('web')->user();
        $org   = $this->resolveOrganization($request);

        if (!$org) {
            abort(404, 'No organization found.');
        }

        if (!$this->policy->viewAny($actor, $org)) {
            abort(403, 'Unauthorized to view users for this organization.');
        }

        $search   = trim((string) $request->input('search', ''));
        $branchId = $request->input('branch_id');
        $roleName = $request->input('role');
        $status   = $request->input('status'); // 'all', 'active', 'disabled'
        $perPage  = min(50, max(10, (int) $request->input('per_page', 20)));

        $allBranchIds = Branch::where('organization_id', $org->id)->pluck('id')->all();

        $query = User::where(function ($q) use ($org, $allBranchIds) {
                $q->whereIn('branch_id', $allBranchIds)
                  ->orWhere('organization_id', $org->id);
            })
            ->where('is_super_admin', false)
            ->whereNotNull('branch_id');

        // Organization Admins cannot see other Organization Admin accounts in branch staff view
        if (!$actor->isSuperAdmin()) {
            $query->whereNotIn('id', function ($q) {
                $q->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'organization_admin')
                    ->where('model_has_roles.model_type', User::class);
            });
        }

        // Search Filter
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Branch Filter
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        // Status Filter
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'disabled') {
            $query->where('is_active', false);
        }

        // Role Filter (using model_has_roles to support team-scoped roles)
        if ($roleName) {
            $query->whereIn('users.id', function ($q) use ($roleName) {
                $q->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', $roleName)
                    ->where('model_has_roles.model_type', User::class);
            });
        }

        $users = $query->with([
            'branch:id,name,slug,is_active',
            'disabledBy:id,name,username',
        ])
        ->orderBy('name', 'asc')
        ->paginate($perPage)
        ->withQueryString();

        $userIds = $users->pluck('id')->all();
        $rolesByUserId = \Illuminate\Support\Facades\DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('model_has_roles.model_id', $userIds)
            ->select('model_has_roles.model_id', 'roles.id', 'roles.name', 'model_has_roles.team_id')
            ->get()
            ->groupBy('model_id');

        // Transform collection to match contract and avoid leaking sensitive data
        $users->through(function (User $u) use ($actor, $rolesByUserId) {
            $userRoles = ($rolesByUserId->get($u->id) ?? collect())->map(fn ($r) => [
                'id'   => $r->id,
                'name' => $r->name,
            ])->values();

            return [
                'id'              => $u->id,
                'name'            => $u->name,
                'username'        => $u->username,
                'email'           => $u->email,
                'is_active'       => (bool) $u->is_active,
                'disabled_at'     => $u->disabled_at?->toIso8601String(),
                'disabled_reason' => $u->disabled_reason,
                'disabled_by'     => $u->disabledBy ? ['id' => $u->disabledBy->id, 'name' => $u->disabledBy->name] : null,
                'branch'          => $u->branch ? [
                    'id'        => $u->branch->id,
                    'name'      => $u->branch->name,
                    'slug'      => $u->branch->slug,
                    'is_active' => (bool) $u->branch->is_active,
                    'pos_url'   => url("/{$u->branch->slug}"),
                ] : null,
                'roles'           => $userRoles,
                'can_move'        => $this->policy->move($actor, $u),
                'can_disable'     => $this->policy->disable($actor, $u),
                'can_enable'      => $this->policy->enable($actor, $u),
                'created_at'      => $u->created_at?->toIso8601String(),
            ];
        });

        // Branches in this organization
        $branches = Branch::where('organization_id', $org->id)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'slug', 'is_active']);

        return Inertia::render('Org/Users/Index', [
            'organization' => [
                'id'               => $org->id,
                'name'             => $org->name,
                'can_manage_staff' => $org->canManageStaff(),
            ],
            'users'           => $users,
            'branches'        => $branches,
            'filters'         => [
                'search'    => $search,
                'branch_id' => $branchId ? (int)$branchId : null,
                'role'      => $roleName,
                'status'    => $status ?: 'all',
                'per_page'  => $perPage,
            ],
            'can_manage_staff' => $org->canManageStaff(),
        ]);
    }

    /**
     * Show detailed user profile and branch history timeline.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $actor = Auth::guard('web')->user();

        if (!$this->policy->view($actor, $user)) {
            abort(403, 'Unauthorized to view this user.');
        }

        $user->load([
            'branch:id,name,slug,is_active',
            'branchHistories.branch:id,name,slug',
            'branchHistories.mover:id,name,username',
            'disabledBy:id,name,username',
        ]);

        $userRoles = \Illuminate\Support\Facades\DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $user->id)
            ->select('roles.id', 'roles.name')
            ->get();

        return response()->json([
            'id'              => $user->id,
            'name'            => $user->name,
            'username'        => $user->username,
            'email'           => $user->email,
            'is_active'       => (bool) $user->is_active,
            'disabled_at'     => $user->disabled_at?->toIso8601String(),
            'disabled_reason' => $user->disabled_reason,
            'disabled_by'     => $user->disabledBy ? ['id' => $user->disabledBy->id, 'name' => $user->disabledBy->name] : null,
            'branch'          => $user->branch ? [
                'id'        => $user->branch->id,
                'name'      => $user->branch->name,
                'slug'      => $user->branch->slug,
                'is_active' => (bool) $user->branch->is_active,
                'pos_url'   => url("/{$user->branch->slug}"),
            ] : null,
            'roles'           => $userRoles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values(),
            'history'         => $user->branchHistories->map(function ($h) {
                return [
                    'id'          => $h->id,
                    'branch'      => $h->branch ? ['id' => $h->branch->id, 'name' => $h->branch->name] : null,
                    'started_at'  => $h->started_at?->toIso8601String(),
                    'ended_at'    => $h->ended_at?->toIso8601String(),
                    'is_current'  => $h->ended_at === null,
                    'reason'      => $h->reason,
                    'moved_by'    => $h->mover ? ['id' => $h->mover->id, 'name' => $h->mover->name] : null,
                ];
            }),
            'created_at'      => $user->created_at?->toIso8601String(),
        ]);
    }

    /**
     * Run pre-flight checks and return destination branch roles before move.
     */
    public function precheckMove(Request $request, User $user): JsonResponse
    {
        $actor = Auth::guard('web')->user();

        if (!$this->policy->move($actor, $user)) {
            return response()->json(['can_move' => false, 'blocks' => ['You are not authorized to transfer this user.']], 403);
        }

        $destinationBranchId = $request->query('destination_branch_id');
        if (!$destinationBranchId) {
            return response()->json(['can_move' => false, 'blocks' => ['Please choose a destination branch.']], 422);
        }

        $destinationBranch = Branch::find($destinationBranchId);
        if (!$destinationBranch) {
            return response()->json(['can_move' => false, 'blocks' => ['Destination branch not found.']], 404);
        }

        $checkResult = $this->moveAction->precheck($actor, $user, $destinationBranch);

        // Fetch available roles at destination branch
        setPermissionsTeamId($destinationBranch->id);
        $destinationBranch->ensureDefaultRoles();
        $roles = Role::where('team_id', $destinationBranch->id)->get(['id', 'name']);

        // Determine matching default role
        $currentUserRole = \Illuminate\Support\Facades\DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.team_id', $user->branch_id)
            ->value('roles.name') ?? 'cashier';

        return response()->json(array_merge($checkResult, [
            'roles'                => $roles,
            'suggested_role'       => $currentUserRole,
            'destination_pos_url'  => url("/{$destinationBranch->slug}"),
        ]));
    }

    /**
     * Execute branch transfer.
     */
    public function move(Request $request, User $user): RedirectResponse
    {
        $actor = Auth::guard('web')->user();

        if (!$this->policy->move($actor, $user)) {
            abort(403, 'You are not authorized to transfer this user.');
        }

        $data = $request->validate([
            'destination_branch_id' => 'required|integer|exists:branches,id',
            'destination_role'      => 'required|string|max:100',
            'reason'                => 'required|string|max:500',
        ]);

        $destinationBranch = Branch::findOrFail($data['destination_branch_id']);

        $result = $this->moveAction->execute(
            $actor,
            $user,
            $destinationBranch,
            $data['destination_role'],
            $data['reason']
        );

        return back()
            ->with('success', $result['message'])
            ->with('transferred_user_name', $user->name)
            ->with('new_login_url', $result['new_login_url']);
    }

    /**
     * Disable staff user account.
     */
    public function disable(Request $request, User $user): RedirectResponse
    {
        $actor = Auth::guard('web')->user();

        if (!$this->policy->disable($actor, $user)) {
            abort(403, 'You are not authorized to disable this user.');
        }

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->disableAction->execute($actor, $user, $data['reason']);

        return back()->with('success', "Account for '{$user->name}' disabled successfully.");
    }

    /**
     * Re-enable staff user account.
     */
    public function enable(Request $request, User $user): RedirectResponse
    {
        $actor = Auth::guard('web')->user();

        if (!$this->policy->enable($actor, $user)) {
            abort(403, 'You are not authorized to enable this user.');
        }

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->enableAction->execute($actor, $user, $data['reason'] ?? null);

        return back()->with('success', "Account for '{$user->name}' has been re-enabled.");
    }
}
