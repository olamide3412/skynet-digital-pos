<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemSettingController extends Controller
{
    public const THEME_PALETTES = [
        [
            'key'          => 'skynet',
            'name'         => 'Skynet Digital Theme',
            'description'  => 'Official Skynet Electric Purple & Gold brand theme',
            'hex'          => '#7B00FF',
            'secondaryHex' => '#FBA43D',
        ],
        [
            'key'          => 'emerald',
            'name'         => 'Emerald Green',
            'description'  => 'Classic green with warm gold secondary accent',
            'hex'          => '#10b981',
            'secondaryHex' => '#f59e0b',
        ],
        [
            'key'          => 'blue',
            'name'         => 'Ocean Blue',
            'description'  => 'Professional corporate blue & amber theme',
            'hex'          => '#3b82f6',
            'secondaryHex' => '#f59e0b',
        ],
        [
            'key'          => 'indigo',
            'name'         => 'Royal Indigo',
            'description'  => 'Modern deep indigo & pink rose theme',
            'hex'          => '#6366f1',
            'secondaryHex' => '#ec4899',
        ],
        [
            'key'          => 'violet',
            'name'         => 'Deep Violet',
            'description'  => 'Vibrant violet & amber gold theme',
            'hex'          => '#8b5cf6',
            'secondaryHex' => '#f59e0b',
        ],
        [
            'key'          => 'teal',
            'name'         => 'Cyber Teal',
            'description'  => 'Sleek cyber teal & warm orange theme',
            'hex'          => '#14b8a6',
            'secondaryHex' => '#f97316',
        ],
        [
            'key'          => 'cyan',
            'name'         => 'Electric Cyan',
            'description'  => 'Bright cyan & amber gold theme',
            'hex'          => '#06b6d4',
            'secondaryHex' => '#f59e0b',
        ],
        [
            'key'          => 'amber',
            'name'         => 'Amber Gold',
            'description'  => 'Warm gold & royal indigo secondary theme',
            'hex'          => '#f59e0b',
            'secondaryHex' => '#6366f1',
        ],
        [
            'key'          => 'rose',
            'name'         => 'Rose Crimson',
            'description'  => 'Bold crimson & ocean blue secondary theme',
            'hex'          => '#f43f5e',
            'secondaryHex' => '#3b82f6',
        ],
    ];

    public function index()
    {
        $logoPath = SystemSetting::get('company_logo_path');

        return Inertia::render('SuperAdmin/Settings', [
            'currentTheme'       => SystemSetting::get('primary_color_theme', 'skynet'),
            'customPrimaryHex'   => SystemSetting::get('custom_primary_hex', '#7B00FF'),
            'customSecondaryHex' => SystemSetting::get('custom_secondary_hex', '#FBA43D'),
            'companyName'        => SystemSetting::get('company_name', 'Skynet POS'),
            'companyShortName'   => SystemSetting::get('company_short_name', 'Skynet'),
            'appTagline'         => SystemSetting::get('app_tagline', 'Digital POS & Inventory Terminal'),
            'currencySymbol'     => SystemSetting::get('currency_symbol', '₦'),
            'supportPhone'       => SystemSetting::get('support_phone', '+234 803 207 2831'),
            'supportEmail'       => SystemSetting::get('support_email', 'support@skynetdigitalltd.com'),
            'companyLogoUrl'     => $logoPath ? asset('storage/' . $logoPath) : null,
            'themePalettes'      => self::THEME_PALETTES,
        ]);
    }

    public function update(Request $request)
    {
        $validKeys = array_merge(array_column(self::THEME_PALETTES, 'key'), ['custom']);

        $data = $request->validate([
            'primary_color_theme'  => 'sometimes|required|string|in:' . implode(',', $validKeys),
            'custom_primary_hex'   => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'custom_secondary_hex' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'company_name'         => 'nullable|string|max:100',
            'company_short_name'   => 'nullable|string|max:50',
            'app_tagline'          => 'nullable|string|max:150',
            'currency_symbol'      => 'nullable|string|max:10',
            'support_phone'        => 'nullable|string|max:50',
            'support_email'        => 'nullable|email|max:100',
            'company_logo'         => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048',
        ]);

        if ($request->hasFile('company_logo')) {
            $path = $request->file('company_logo')->store('system', 'public');
            SystemSetting::set('company_logo_path', $path);
        }

        unset($data['company_logo']);

        foreach ($data as $key => $value) {
            if ($value !== null) {
                SystemSetting::set($key, $value);
            }
        }

        \App\Services\ActivityLogger::setting('Updated system branding, custom theme colors, and company configuration');

        return back()->with('success', 'Global system branding and parameters saved successfully.');
    }
}
