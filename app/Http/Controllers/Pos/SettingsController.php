<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        $branch   = current_branch();
        $settings = PosSettings::current();

        // Merge branch business details into the settings object for the form
        $settingsData = $settings->toArray();
        if ($branch) {
            $settingsData['business_name']           = $branch->name;
            $settingsData['business_address']        = $branch->address;
            $settingsData['business_contact_number'] = $branch->phone;
            $settingsData['business_email']          = $branch->email;
        }

        return Inertia::render('Settings/Index', [
            'settings' => $settingsData,
        ]);
    }

    public function update(Request $request)
    {
        if (!\App\Services\RoleService::canEditSettings()) {
            abort(403, 'Only admins can update settings.');
        }

        $data = $request->validate([
            'business_name'            => 'required|string|max:50',
            'business_address'         => 'nullable|string|max:100',
            'business_contact_number'  => 'nullable|string|max:50',
            'business_email'           => 'nullable|email|max:50',
            'sell_interface'           => 'required|in:classic,gallery',
            'is_price_editable'        => 'nullable|boolean',
            'is_qty_deduction'         => 'nullable|boolean',
            'out_of_stock'             => 'required|integer|min:0',
            'is_check_expiration'      => 'nullable|boolean',
            'is_show_buy_price'        => 'nullable|boolean',
            'is_use_profit_percentage' => 'nullable|boolean',
            'is_tax_enabled'           => 'nullable|boolean',
            'tax_percentage'           => 'nullable|numeric|min:0|max:100',
            'wholesale_profit_percent' => 'required|numeric|min:0',
            'consumer_profit_percent'  => 'required|numeric|min:0',
            'business_sector'          => 'required|in:health,commerce',
        ]);

        // ── 1. Update branch business details ─────────────────────────────────
        $branch = current_branch();
        if ($branch) {
            $branch->update([
                'name'    => $data['business_name'],
                'address' => $data['business_address'] ?? $branch->address,
                'phone'   => $data['business_contact_number'] ?? $branch->phone,
                'email'   => $data['business_email'] ?? $branch->email,
            ]);
        }

        // ── 2. Update POS operational settings ────────────────────────────────
        $settingsData = array_diff_key($data, array_flip([
            'business_name', 'business_address', 'business_contact_number', 'business_email',
        ]));

        $settings = PosSettings::current();
        $settings->update($settingsData);

        // ── 3. Recalculate item prices if auto profit percentage is enabled ────
        if (!empty($data['is_use_profit_percentage'])) {
            $consumerProfit  = (float) ($data['consumer_profit_percent'] ?? 15);
            $wholesaleProfit = (float) ($data['wholesale_profit_percent'] ?? 10);

            if ($branch) {
                \App\Models\Item::where('branch_id', $branch->id)
                    ->where(function ($q) {
                        $q->where('price_locked', false)
                          ->orWhereNull('price_locked');
                    })
                    ->get()
                    ->each(function ($item) use ($consumerProfit, $wholesaleProfit) {
                        $buyPrice = (float) $item->buy_price;
                        if ($buyPrice > 0) {
                            $item->update([
                                'price'           => round($buyPrice * (1 + $consumerProfit / 100), 2),
                                'wholesale_price' => round($buyPrice * (1 + $wholesaleProfit / 100), 2),
                            ]);
                        }
                    });
            }
        }

        return back()->with('success', 'Settings updated successfully.');
    }

    public function apiShow()
    {
        return response()->json(PosSettings::current());
    }
}
