<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\OrganizationActivityLog;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    /**
     * Display a listing of client organizations.
     */
    public function index(Request $request): Response
    {
        $search = trim($request->input('search', ''));

        $query = Organization::withCount(['branches', 'users'])
            ->with(['branches' => fn($q) => $q->select('id', 'organization_id', 'name', 'slug', 'is_active')])
            ->when($search, function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });

        $organizations = $query->latest()->paginate(15)->withQueryString();

        $stats = [
            'total_organizations' => Organization::count(),
            'active_organizations' => Organization::where('status', 'active')->count(),
            'suspended_organizations' => Organization::where('status', 'suspended')->count(),
            'total_branches'      => Branch::count(),
        ];

        return Inertia::render('SuperAdmin/Organizations/Index', [
            'organizations' => $organizations,
            'stats'         => $stats,
            'filters'       => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created organization.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255|unique:organizations,name',
            'contact_name'        => 'nullable|string|max:255',
            'contact_email'       => 'nullable|email|max:255',
            'contact_phone'       => 'nullable|string|max:50',
            'address'             => 'nullable|string|max:500',
            'can_create_branches' => 'nullable|boolean',
            'notes'               => 'nullable|string|max:2000',
        ]);

        $data['status'] = 'active';
        $data['can_create_branches'] = $request->has('can_create_branches') ? $request->boolean('can_create_branches') : true;

        $organization = DB::transaction(function () use ($data) {
            $org = Organization::create($data);

            OrganizationActivityLog::create([
                'organization_id' => $org->id,
                'actor_user_id'   => Auth::id(),
                'action'          => 'organization_created',
                'details'         => [
                    'organization_name' => $org->name,
                    'created_by'        => Auth::user()->name,
                ],
                'created_at'      => now(),
            ]);

            ActivityLogger::log(
                'setting',
                "Platform Super Admin created organization '{$org->name}'"
            );

            return $org;
        });

        return redirect()->route('superadmin.organizations.show', $organization->id)
            ->with('success', "Organization '{$organization->name}' created successfully.");
    }

    /**
     * Display the specified organization details.
     */
    public function show(Organization $organization): Response
    {
        $organization->loadCount(['branches', 'users']);

        $branches = $organization->branches()
            ->withCount(['users', 'items', 'sales'])
            ->latest()
            ->get()
            ->map(fn(Branch $b) => [
                'id'          => $b->id,
                'name'        => $b->name,
                'slug'        => $b->slug,
                'address'     => $b->address,
                'phone'       => $b->phone,
                'is_active'   => (bool) $b->is_active,
                'users_count' => $b->users_count,
                'items_count' => $b->items_count,
                'sales_count' => $b->sales_count,
            ]);

        $adminUsers = $organization->adminUsers()
            ->latest()
            ->get(['id', 'name', 'username', 'email', 'is_active', 'created_at']);

        $activityLogs = OrganizationActivityLog::where('organization_id', $organization->id)
            ->with('actor:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit(25)
            ->get();

        $allOrganizations = Organization::where('id', '!=', $organization->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Subscription details for this organization
        $subscription = $organization->subscription()
            ->with(['pricingPlan', 'changedBy:id,name'])
            ->first();

        $subscriptionData = null;
        if ($subscription) {
            $subscriptionData = [
                'id'                 => $subscription->id,
                'pricing_plan_id'    => $subscription->pricing_plan_id,
                'plan_name'          => $subscription->pricingPlan?->name,
                'plan_tagline'       => $subscription->pricingPlan?->tagline,
                'started_at'         => $subscription->started_at?->format('Y-m-d'),
                'renewal_date'       => $subscription->renewal_date?->format('Y-m-d'),
                'renewal_date_human' => $subscription->renewal_date?->format('d M Y'),
                'status'             => $subscription->status,
                'computed_status'    => $subscription->computedStatus(),
                'days_until_renewal' => $subscription->daysUntilRenewal(),
                'is_expiring_soon'   => $subscription->isExpiringSoon(),
                'is_past_due'        => $subscription->isPastDue(),
                'changed_by'         => $subscription->changedBy?->name,
                'notes'              => $subscription->notes,
                'updated_at'         => $subscription->updated_at?->format('d M Y, H:i'),
            ];
        }

        $subscriptionHistories = $organization->subscriptionHistories()
            ->with(['pricingPlan', 'changedBy:id,name'])
            ->limit(20)
            ->get()
            ->map(fn($sh) => [
                'id'                 => $sh->id,
                'plan_name'          => $sh->pricingPlan?->name ?? 'Unknown',
                'started_at'         => $sh->started_at?->format('d M Y'),
                'renewal_date'       => $sh->renewal_date?->format('d M Y'),
                'status'             => $sh->status,
                'changed_by'         => $sh->changedBy?->name ?? 'System',
                'notes'              => $sh->notes,
                'created_at'         => $sh->created_at?->format('d M Y, H:i'),
            ]);

        $pricingPlans = PricingPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'tagline', 'first_year_price', 'renewal_price', 'branches_limit', 'users_limit', 'ai_credits']);

        return Inertia::render('SuperAdmin/Organizations/Show', [
            'organization'          => $organization,
            'branches'              => $branches,
            'adminUsers'            => $adminUsers,
            'activityLogs'          => $activityLogs,
            'allOrganizations'      => $allOrganizations,
            'subscription'          => $subscriptionData,
            'subscriptionHistories' => $subscriptionHistories,
            'pricingPlans'          => $pricingPlans,
        ]);
    }

    /**
     * Update the specified organization.
     */
    public function update(Request $request, Organization $organization)
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')->ignore($organization->id)],
            'contact_name'        => 'nullable|string|max:255',
            'contact_email'       => 'nullable|email|max:255',
            'contact_phone'       => 'nullable|string|max:50',
            'address'             => 'nullable|string|max:500',
            'status'              => 'required|in:active,suspended',
            'can_create_branches' => 'nullable|boolean',
            'notes'               => 'nullable|string|max:2000',
        ]);

        if ($request->has('can_create_branches')) {
            $data['can_create_branches'] = $request->boolean('can_create_branches');
        }

        $oldStatus = $organization->status;
        $oldCanCreateBranches = $organization->can_create_branches;
        $organization->update($data);

        OrganizationActivityLog::create([
            'organization_id' => $organization->id,
            'actor_user_id'   => Auth::id(),
            'action'          => 'organization_updated',
            'details'         => [
                'updated_fields'              => array_keys($data),
                'status_changed'              => $oldStatus !== $organization->status,
                'old_status'                  => $oldStatus,
                'new_status'                  => $organization->status,
                'can_create_branches_changed' => isset($data['can_create_branches']) && $oldCanCreateBranches !== $organization->can_create_branches,
                'can_create_branches'         => $organization->can_create_branches,
            ],
            'created_at'      => now(),
        ]);

        ActivityLogger::log(
            'setting',
            "Platform Super Admin updated organization profile for '{$organization->name}'"
        );

        return back()->with('success', "Organization '{$organization->name}' updated successfully.");
    }

    /**
     * Toggle whether this organization can create its own branches.
     */
    public function toggleBranchCreation(Organization $organization)
    {
        $organization->can_create_branches = !$organization->can_create_branches;
        $organization->save();

        $statusText = $organization->can_create_branches ? 'enabled' : 'disabled';

        OrganizationActivityLog::create([
            'organization_id' => $organization->id,
            'actor_user_id'   => Auth::id(),
            'action'          => "branch_creation_{$statusText}",
            'details'         => [
                'can_create_branches' => $organization->can_create_branches,
            ],
            'created_at'      => now(),
        ]);

        ActivityLogger::log(
            'setting',
            "Platform Super Admin {$statusText} branch creation for organization '{$organization->name}'"
        );

        return back()->with('success', "Branch creation has been {$statusText} for {$organization->name}.");
    }

    /**
     * Remove the specified organization from storage.
     */
    public function destroy(Organization $organization)
    {
        if ($organization->branches()->count() > 0) {
            return back()->withErrors([
                'organization' => "Cannot delete organization '{$organization->name}' because it currently has {$organization->branches()->count()} branch(es) attached. Please move or delete the branches first.",
            ]);
        }

        $name = $organization->name;
        $orgId = $organization->id;

        DB::transaction(function () use ($organization, $name, $orgId) {
            OrganizationActivityLog::create([
                'organization_id' => null,
                'actor_user_id'   => Auth::id(),
                'action'          => 'organization_deleted',
                'details'         => [
                    'organization_id'   => $orgId,
                    'organization_name' => $name,
                ],
                'created_at'      => now(),
            ]);

            $organization->delete();

            ActivityLogger::log(
                'setting',
                "Platform Super Admin deleted empty organization '{$name}'"
            );
        });

        return redirect()->route('superadmin.organizations.index')
            ->with('success', "Organization '{$name}' deleted successfully.");
    }

    /**
     * Create an Organization Admin user bound to this organization.
     */
    public function storeAdminUser(Request $request, Organization $organization)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users,username',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $user = DB::transaction(function () use ($data, $organization) {
            $user = User::create([
                'name'            => $data['name'],
                'full_name'       => $data['name'],
                'username'        => $data['username'],
                'email'           => $data['email'],
                'password'        => Hash::make($data['password']),
                'is_active'       => true,
                'is_super_admin'  => false,
                'branch_id'       => null,
                'organization_id' => $organization->id,
            ]);

            // Assign organization_admin role (global team context)
            setPermissionsTeamId(0);
            $user->assignRole('organization_admin');

            OrganizationActivityLog::create([
                'organization_id' => $organization->id,
                'actor_user_id'   => Auth::id(),
                'action'          => 'admin_user_created',
                'details'         => [
                    'user_id'   => $user->id,
                    'username'  => $user->username,
                    'email'     => $user->email,
                ],
                'created_at'      => now(),
            ]);

            return $user;
        });

        return back()->with('success', "Organization Admin @{$user->username} created successfully for {$organization->name}.");
    }

    /**
     * Toggle Organization Admin active status.
     */
    public function toggleAdminUser(Organization $organization, User $user)
    {
        if ($user->organization_id !== $organization->id) {
            abort(403, 'User does not belong to this organization.');
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'enabled' : 'disabled';

        OrganizationActivityLog::create([
            'organization_id' => $organization->id,
            'actor_user_id'   => Auth::id(),
            'action'          => "admin_user_{$status}",
            'details'         => [
                'user_id'  => $user->id,
                'username' => $user->username,
            ],
            'created_at'      => now(),
        ]);

        return back()->with('success', "Organization Admin @{$user->username} {$status} successfully.");
    }

    /**
     * Reset Organization Admin password.
     */
    public function resetAdminPassword(Request $request, Organization $organization, User $user)
    {
        if ($user->organization_id !== $organization->id) {
            abort(403, 'User does not belong to this organization.');
        }

        $data = $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $user->password = Hash::make($data['password']);
        $user->save();

        OrganizationActivityLog::create([
            'organization_id' => $organization->id,
            'actor_user_id'   => Auth::id(),
            'action'          => 'admin_user_password_reset',
            'details'         => [
                'user_id'  => $user->id,
                'username' => $user->username,
            ],
            'created_at'      => now(),
        ]);

        return back()->with('success', "Password for @{$user->username} reset successfully.");
    }

    /**
     * Update or set the organization's subscription plan.
     */
    public function updateSubscription(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'pricing_plan_id' => 'required|exists:pricing_plans,id',
            'started_at'      => 'required|date',
            'renewal_date'    => 'required|date',
            'status'          => 'required|in:active,grace_period,expired',
            'notes'           => 'nullable|string|max:500',
        ]);

        $actorId = Auth::id();

        DB::transaction(function () use ($organization, $validated, $actorId) {
            Subscription::updateOrCreate(
                ['organization_id' => $organization->id],
                [
                    'pricing_plan_id' => $validated['pricing_plan_id'],
                    'started_at'      => $validated['started_at'],
                    'renewal_date'    => $validated['renewal_date'],
                    'status'          => $validated['status'],
                    'changed_by'      => $actorId,
                    'notes'           => $validated['notes'] ?? null,
                ]
            );

            SubscriptionHistory::create([
                'organization_id' => $organization->id,
                'pricing_plan_id' => $validated['pricing_plan_id'],
                'started_at'      => $validated['started_at'],
                'renewal_date'    => $validated['renewal_date'],
                'status'          => $validated['status'],
                'changed_by'      => $actorId,
                'notes'           => $validated['notes'] ?? null,
            ]);

            $plan = PricingPlan::find($validated['pricing_plan_id']);

            OrganizationActivityLog::create([
                'organization_id' => $organization->id,
                'actor_user_id'   => $actorId,
                'action'          => 'subscription_updated',
                'details'         => [
                    'pricing_plan_id' => $validated['pricing_plan_id'],
                    'plan_name'       => $plan?->name,
                    'renewal_date'    => $validated['renewal_date'],
                    'status'          => $validated['status'],
                ],
                'created_at'      => now(),
            ]);
        });

        return back()->with('success', "Subscription plan updated successfully for {$organization->name}.");
    }
}
