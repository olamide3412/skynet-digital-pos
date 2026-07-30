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
        return Inertia::render('Settings/Index', [
            'settings' => PosSettings::current(),
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
            'wholesale_profit_percent' => 'required|numeric|min:0',
            'consumer_profit_percent'  => 'required|numeric|min:0',
            'business_sector'          => 'required|in:health,commerce',
        ]);

        PosSettings::current()->update($data);

        return back()->with('success', 'Settings updated successfully.');
    }

    public function apiShow()
    {
        return response()->json(PosSettings::current());
    }
}
