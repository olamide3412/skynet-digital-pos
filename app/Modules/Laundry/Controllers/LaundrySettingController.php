<?php

namespace App\Modules\Laundry\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PosSettings;
use App\Modules\Laundry\Models\LaundrySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LaundrySettingController extends Controller
{
    /**
     * Show laundry configuration page for the branch.
     */
    public function index(): Response
    {
        $user = auth()->user();
        if (!$user->isBranchAdmin() && !$user->isSuperAdmin() && !$user->hasPermissionTo('laundry.manage_settings')) {
            abort(403, 'Unauthorized to manage laundry settings.');
        }

        $branch = current_branch();
        $laundrySettings = LaundrySetting::getForBranch($branch->id);
        $posSettings = PosSettings::forBranch($branch->id);

        return Inertia::render('Laundry/Settings/Index', [
            'settings' => $laundrySettings,
            'branch'   => [
                'id'                => $branch->id,
                'name'              => $branch->name,
                'laundry_enabled'   => $branch->laundryEnabled(),
                'pos_enabled'       => $branch->posEnabled(),
                'default_interface' => $posSettings->default_interface ?? 'pos',
            ],
            'can_manage' => $user->isBranchAdmin() || $user->isSuperAdmin() || $user->hasPermissionTo('laundry.manage_settings'),
        ]);
    }

    /**
     * Update branch laundry settings.
     */
    public function update(Request $request)
    {
        $user = auth()->user();
        if (!$user->isBranchAdmin() && !$user->isSuperAdmin() && !$user->hasPermissionTo('laundry.manage_settings')) {
            abort(403, 'Unauthorized to manage laundry settings.');
        }

        $branch = current_branch();

        $validated = $request->validate([
            'default_turnaround_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'unclaimed_after_days'     => ['required', 'integer', 'min:1', 'max:365'],
            'order_prefix'             => ['required', 'string', 'max:10'],
            'whatsapp_template'        => ['required', 'string', 'max:1000'],
            'default_country_code'     => ['required', 'string', 'max:5'],
            'receipt_footer'           => ['nullable', 'string', 'max:500'],
            'allow_price_override'     => ['boolean'],
            'allow_unpaid_pickup'      => ['boolean'],
            'default_interface'        => ['nullable', 'string', 'in:pos,laundry'],
        ]);

        $settings = LaundrySetting::getForBranch($branch->id);
        $settings->update([
            'default_turnaround_hours' => $validated['default_turnaround_hours'],
            'unclaimed_after_days'     => $validated['unclaimed_after_days'],
            'order_prefix'             => strtoupper(trim($validated['order_prefix'])),
            'whatsapp_template'        => $validated['whatsapp_template'],
            'default_country_code'     => preg_replace('/\D/', '', $validated['default_country_code']),
            'receipt_footer'           => $validated['receipt_footer'] ?? null,
            'allow_price_override'     => (bool) ($validated['allow_price_override'] ?? false),
            'allow_unpaid_pickup'      => (bool) ($validated['allow_unpaid_pickup'] ?? false),
        ]);

        // Branch Admin can switch default_interface if both POS & Laundry are enabled
        if (isset($validated['default_interface']) && $branch->laundryEnabled() && $branch->posEnabled()) {
            $posSettings = PosSettings::forBranch($branch->id);
            $posSettings->update([
                'default_interface' => $validated['default_interface'],
            ]);
        }

        return back()->with('success', 'Laundry settings updated successfully.');
    }
}
