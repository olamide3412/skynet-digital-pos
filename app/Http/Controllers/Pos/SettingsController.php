<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosSettings;
use App\Modules\Laundry\Models\LaundrySetting;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request $request)
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
            $settingsData['logo_url']                = $branch->logo_path ? asset('storage/' . $branch->logo_path) : null;
            $settingsData['public_catalog_enabled']   = (bool) $branch->public_catalog_enabled;
            $settingsData['public_catalog_url']       = route('catalog.show', ['slug' => $branch->slug]);
            $settingsData['branch_slug']              = $branch->slug;
            $settingsData['laundry_enabled']          = $branch->laundryEnabled();
            $settingsData['pos_enabled']              = $branch->posEnabled();
        }

        $laundrySettingsData = null;
        if ($branch && $branch->laundryEnabled()) {
            $lSetting = LaundrySetting::forBranch($branch->id);
            $laundrySettingsData = [
                'default_turnaround_hours' => $lSetting->default_turnaround_hours,
                'default_turnaround_days'  => $lSetting->default_turnaround_days,
                'unclaimed_after_days'     => $lSetting->unclaimed_after_days,
                'order_prefix'             => $lSetting->order_prefix,
                'whatsapp_template'        => $lSetting->whatsapp_template,
                'default_country_code'     => $lSetting->default_country_code,
                'receipt_footer'           => $lSetting->receipt_footer,
                'allow_price_override'     => (bool) $lSetting->allow_price_override,
                'allow_unpaid_pickup'      => (bool) $lSetting->allow_unpaid_pickup,
            ];
        }

        $branchItems = $branch ? \App\Models\Item::where('branch_id', $branch->id)
            ->select(['id', 'item_name', 'barcode_number', 'price', 'front_store_qty', 'image_path'])
            ->orderBy('item_name')
            ->get()
            ->map(fn($item) => [
                'id'              => $item->id,
                'item_name'       => $item->item_name,
                'barcode_number'  => $item->barcode_number,
                'price'           => (float) $item->price,
                'front_store_qty' => (int) $item->front_store_qty,
                'image_url'       => $item->image_url,
            ]) : [];

        $categories = $branch ? \App\Models\Category::where(function ($q) use ($branch) {
            $q->where('branch_id', $branch->id)->orWhereNull('branch_id');
        })->orderBy('name')->get(['id', 'name']) : [];

        $user = auth()->user();
        $isFullAdmin = $user && ($user->isSuperAdmin() || $user->isBranchAdmin() || $user->isOrganizationAdmin());

        return Inertia::render('Settings/Index', [
            'settings'              => $settingsData,
            'laundry_settings'      => $laundrySettingsData,
            'branchItems'           => $branchItems,
            'categories'            => $categories,
            'is_accounting_enabled' => $branch?->organization?->hasFeature('accounting') ?? false,
            'initial_tab'           => $request->query('tab', 'general'),
            'can_manage_laundry'    => $isFullAdmin || RoleService::can('laundry.manage_settings'),
            'can_manage_pos'        => $isFullAdmin || RoleService::can('canEditSettings'),
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $isFullAdmin = $user && ($user->isSuperAdmin() || $user->isBranchAdmin() || $user->isOrganizationAdmin());
        $canEditPos = $isFullAdmin || RoleService::can('canEditSettings');
        $canEditLaundry = $isFullAdmin || RoleService::can('laundry.manage_settings');

        if (!$canEditPos && !$canEditLaundry) {
            abort(403, 'Unauthorized to update settings.');
        }

        $branch = current_branch();
        $tab = $request->input('active_tab', $request->input('tab', 'all'));

        // ── 1. Tab: General Settings (Shared) ─────────────────────────────────
        if ($tab === 'general') {
            $data = $request->validate([
                'business_name'            => 'required|string|max:100',
                'business_address'         => 'nullable|string|max:255',
                'business_contact_number'  => 'nullable|string|max:50',
                'business_email'           => 'nullable|email|max:100',
                'logo'                     => 'nullable|image|max:2048',
                'require_till_opening'     => 'nullable|boolean',
                'print_till_slip'          => 'nullable|boolean',
                'default_interface'        => 'nullable|string|in:pos,laundry',
                'business_sector'          => 'nullable|in:health,commerce',
            ]);

            if ($branch) {
                $branchData = [
                    'name'    => $data['business_name'],
                    'address' => $data['business_address'] ?? $branch->address,
                    'phone'   => $data['business_contact_number'] ?? $branch->phone,
                    'email'   => $data['business_email'] ?? $branch->email,
                ];

                if ($request->hasFile('logo')) {
                    if ($branch->logo_path) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($branch->logo_path);
                    }
                    $branchData['logo_path'] = $request->file('logo')->store('branch_logos', 'public');
                }

                $branch->update($branchData);
            }

            $settings = PosSettings::current();
            $posUpdates = [
                'require_till_opening' => !empty($data['require_till_opening']),
                'print_till_slip'      => !empty($data['print_till_slip']),
            ];
            if (!empty($data['default_interface'])) {
                $posUpdates['default_interface'] = $data['default_interface'];
            }
            if (!empty($data['business_sector'])) {
                $posUpdates['business_sector'] = $data['business_sector'];
            }
            $settings->update($posUpdates);

            return back()->with('success', 'General business & shift accountability settings saved.');
        }

        // ── 2. Tab: Retail & POS Settings ──────────────────────────────────────
        if ($tab === 'pos') {
            if (!$canEditPos) {
                abort(403, 'Unauthorized to update POS settings.');
            }

            $data = $request->validate([
                'sell_interface'                => 'required|in:classic,gallery',
                'quick_access_mode'             => 'required|string|in:none,top_sellers,recently_sold,pinned_items,category_shortcuts',
                'pinned_quick_access_items'     => 'nullable|array|max:10',
                'pinned_quick_access_items.*'   => 'integer',
                'quick_access_category_ids'     => 'nullable|array|max:6',
                'quick_access_category_ids.*'   => 'integer',
                'is_price_editable'             => 'nullable|boolean',
                'is_qty_deduction'              => 'nullable|boolean',
                'is_offline_qty_deduction'      => 'nullable|boolean',
                'out_of_stock'                  => 'required|integer|min:0',
                'is_check_expiration'           => 'nullable|boolean',
                'batch_tracking_enabled'        => 'nullable|boolean',
                'expiry_alert_days'             => 'nullable|integer|min:1',
                'is_imei_enabled'               => 'nullable|boolean',
                'is_camera_scanner_enabled'     => 'nullable|boolean',
                'is_grid_separate_rows'         => 'nullable|boolean',
                'is_cart_auto_scroll_highlight' => 'nullable|boolean',
                'is_show_buy_price'             => 'nullable|boolean',
                'is_use_profit_percentage'      => 'nullable|boolean',
                'wholesale_profit_percent'      => 'required|numeric|min:0',
                'consumer_profit_percent'       => 'required|numeric|min:0',
                'receipt_paper_size'            => 'required|string|in:80mm,A4,a4',
                'receipt_printer_name'          => 'nullable|string|max:100',
                'printer_type'                  => 'required|string|in:thermal_80mm,thermal_58mm,a4_standard',
                'printer_connection'            => 'required|string|in:kiosk_direct,network_ip,local_agent',
                'printer_ip_address'            => 'nullable|string|max:100',
                'auto_print_receipt'            => 'nullable|boolean',
                'show_receipt_preview'          => 'nullable|boolean',
                'public_catalog_enabled'        => 'nullable|boolean',
            ]);

            if ($branch) {
                $branch->update([
                    'public_catalog_enabled' => !empty($data['public_catalog_enabled']),
                ]);
            }

            $settingsData = array_diff_key($data, array_flip(['public_catalog_enabled', '_method', 'active_tab', 'tab']));
            $settings = PosSettings::current();
            $settings->update($settingsData);

            if ($branch) {
                \App\Services\QuickAccessService::clearBranchCache($branch->id);
            }

            if (!empty($data['is_use_profit_percentage']) && $branch) {
                $consumerProfit  = (float) ($data['consumer_profit_percent'] ?? 15);
                $wholesaleProfit = (float) ($data['wholesale_profit_percent'] ?? 10);

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

            return back()->with('success', 'Retail & POS terminal settings saved successfully.');
        }

        // ── 3. Tab: Laundry Module Settings ────────────────────────────────────
        if ($tab === 'laundry') {
            if (!$canEditLaundry) {
                abort(403, 'Unauthorized to update laundry settings.');
            }

            $data = $request->validate([
                'default_turnaround_hours' => ['required', 'integer', 'min:1', 'max:720'],
                'unclaimed_after_days'     => ['required', 'integer', 'min:1', 'max:365'],
                'order_prefix'             => ['required', 'string', 'max:10'],
                'whatsapp_template'        => ['required', 'string', 'max:1000'],
                'default_country_code'     => ['required', 'string', 'max:5'],
                'receipt_footer'           => ['nullable', 'string', 'max:500'],
                'allow_price_override'     => ['nullable', 'boolean'],
                'allow_unpaid_pickup'      => ['nullable', 'boolean'],
            ]);

            if ($branch) {
                $laundrySetting = LaundrySetting::getForBranch($branch->id);
                $laundrySetting->update([
                    'default_turnaround_days' => max(1, (int) round(($data['default_turnaround_hours'] ?? 48) / 24)),
                    'unclaimed_after_days'    => $data['unclaimed_after_days'],
                    'order_prefix'            => strtoupper(trim($data['order_prefix'])),
                    'whatsapp_template'       => $data['whatsapp_template'],
                    'default_country_code'    => preg_replace('/\D/', '', $data['default_country_code']),
                    'receipt_footer'          => $data['receipt_footer'] ?? null,
                    'allow_price_override'    => (bool) ($data['allow_price_override'] ?? false),
                    'allow_unpaid_pickup'     => (bool) ($data['allow_unpaid_pickup'] ?? false),
                ]);
            }

            return back()->with('success', 'Laundry operational & notification settings saved successfully.');
        }

        // ── 4. Tab: Accounting & Tax ──────────────────────────────────────────
        if ($tab === 'accounting') {
            if (!$canEditPos) {
                abort(403, 'Unauthorized to update accounting settings.');
            }

            $data = $request->validate([
                'is_tax_enabled'           => 'nullable|boolean',
                'tax_percentage'           => 'nullable|numeric|min:0|max:100',
                'is_accounting_vat_enabled'=> 'nullable|boolean',
            ]);

            $settings = PosSettings::current();
            $settings->update([
                'is_tax_enabled'            => !empty($data['is_tax_enabled']),
                'tax_percentage'            => (float) ($data['tax_percentage'] ?? 0),
                'is_accounting_vat_enabled' => !empty($data['is_accounting_vat_enabled']),
            ]);

            return back()->with('success', 'Accounting & VAT settings saved successfully.');
        }

        // ── 5. Full Form Save (All together / Legacy fallback) ────────────────
        $data = $request->validate([
            'business_name'            => 'required|string|max:100',
            'business_address'         => 'nullable|string|max:255',
            'business_contact_number'  => 'nullable|string|max:50',
            'business_email'           => 'nullable|email|max:100',
            'logo'                     => 'nullable|image|max:2048',
            'sell_interface'           => 'required|in:classic,gallery',
            'quick_access_mode'        => 'required|string|in:none,top_sellers,recently_sold,pinned_items,category_shortcuts',
            'pinned_quick_access_items'=> 'nullable|array|max:10',
            'pinned_quick_access_items.*' => 'integer',
            'quick_access_category_ids'=> 'nullable|array|max:6',
            'quick_access_category_ids.*' => 'integer',
            'is_price_editable'        => 'nullable|boolean',
            'is_qty_deduction'         => 'nullable|boolean',
            'is_offline_qty_deduction' => 'nullable|boolean',
            'out_of_stock'             => 'required|integer|min:0',
            'is_check_expiration'      => 'nullable|boolean',
            'batch_tracking_enabled'   => 'nullable|boolean',
            'expiry_alert_days'        => 'nullable|integer|min:1',
            'is_imei_enabled'          => 'nullable|boolean',
            'is_camera_scanner_enabled'=> 'nullable|boolean',
            'is_grid_separate_rows'    => 'nullable|boolean',
            'is_cart_auto_scroll_highlight' => 'nullable|boolean',
            'is_show_buy_price'        => 'nullable|boolean',
            'is_use_profit_percentage' => 'nullable|boolean',
            'is_tax_enabled'           => 'nullable|boolean',
            'tax_percentage'           => 'nullable|numeric|min:0|max:100',
            'is_accounting_vat_enabled'=> 'nullable|boolean',
            'wholesale_profit_percent' => 'required|numeric|min:0',
            'consumer_profit_percent'  => 'required|numeric|min:0',
            'business_sector'          => 'required|in:health,commerce',
            'receipt_paper_size'       => 'required|string|in:80mm,A4,a4',
            'receipt_printer_name'     => 'nullable|string|max:100',
            'printer_type'             => 'required|string|in:thermal_80mm,thermal_58mm,a4_standard',
            'printer_connection'       => 'required|string|in:kiosk_direct,network_ip,local_agent',
            'printer_ip_address'       => 'nullable|string|max:100',
            'auto_print_receipt'       => 'nullable|boolean',
            'show_receipt_preview'     => 'nullable|boolean',
            'require_till_opening'     => 'nullable|boolean',
            'print_till_slip'          => 'nullable|boolean',
            'public_catalog_enabled'   => 'nullable|boolean',
            'default_interface'        => 'nullable|string|in:pos,laundry',
            // Optional laundry fields if provided
            'default_turnaround_hours' => 'nullable|integer|min:1|max:720',
            'unclaimed_after_days'     => 'nullable|integer|min:1|max:365',
            'order_prefix'             => 'nullable|string|max:10',
            'whatsapp_template'        => 'nullable|string|max:1000',
            'default_country_code'     => 'nullable|string|max:5',
            'receipt_footer'           => 'nullable|string|max:500',
            'allow_price_override'     => 'nullable|boolean',
            'allow_unpaid_pickup'      => 'nullable|boolean',
        ]);

        if ($branch) {
            $branchData = [
                'name'                   => $data['business_name'],
                'address'                => $data['business_address'] ?? $branch->address,
                'phone'                  => $data['business_contact_number'] ?? $branch->phone,
                'email'                  => $data['business_email'] ?? $branch->email,
                'public_catalog_enabled' => !empty($data['public_catalog_enabled']),
            ];

            if ($request->hasFile('logo')) {
                if ($branch->logo_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($branch->logo_path);
                }
                $branchData['logo_path'] = $request->file('logo')->store('branch_logos', 'public');
            }

            $branch->update($branchData);
        }

        $settingsData = array_diff_key($data, array_flip([
            'business_name', 'business_address', 'business_contact_number', 'business_email', 'logo', '_method',
            'public_catalog_enabled', 'default_turnaround_hours', 'unclaimed_after_days', 'order_prefix',
            'whatsapp_template', 'default_country_code', 'receipt_footer', 'allow_price_override', 'allow_unpaid_pickup', 'active_tab', 'tab'
        ]));

        $settings = PosSettings::current();
        $settings->update($settingsData);

        if ($branch && $branch->laundryEnabled() && isset($data['default_turnaround_hours'])) {
            $laundrySetting = LaundrySetting::getForBranch($branch->id);
            $laundrySetting->update([
                'default_turnaround_days' => max(1, (int) round(($data['default_turnaround_hours'] ?? 48) / 24)),
                'unclaimed_after_days'    => $data['unclaimed_after_days'] ?? $laundrySetting->unclaimed_after_days,
                'order_prefix'            => isset($data['order_prefix']) ? strtoupper(trim($data['order_prefix'])) : $laundrySetting->order_prefix,
                'whatsapp_template'       => $data['whatsapp_template'] ?? $laundrySetting->whatsapp_template,
                'default_country_code'    => isset($data['default_country_code']) ? preg_replace('/\D/', '', $data['default_country_code']) : $laundrySetting->default_country_code,
                'receipt_footer'          => $data['receipt_footer'] ?? $laundrySetting->receipt_footer,
                'allow_price_override'    => (bool) ($data['allow_price_override'] ?? $laundrySetting->allow_price_override),
                'allow_unpaid_pickup'     => (bool) ($data['allow_unpaid_pickup'] ?? $laundrySetting->allow_unpaid_pickup),
            ]);
        }

        if ($branch) {
            \App\Services\QuickAccessService::clearBranchCache($branch->id);
        }

        if (!empty($data['is_use_profit_percentage']) && $branch) {
            $consumerProfit  = (float) ($data['consumer_profit_percent'] ?? 15);
            $wholesaleProfit = (float) ($data['wholesale_profit_percent'] ?? 10);

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

        return back()->with('success', 'All store and module settings updated successfully.');
    }

    public function apiShow()
    {
        return response()->json(PosSettings::current());
    }
}
