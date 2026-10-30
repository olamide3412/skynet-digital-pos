<?php

namespace Database\Seeders;

use App\Models\PlanCapability;
use App\Models\PricingIncludedItem;
use App\Models\PricingPlan;
use App\Models\PricingPlanFeature;
use App\Models\PricingSetting;
use App\Models\PricingValueProp;
use Illuminate\Database\Seeder;

class PricingPlanSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Shared Settings
        PricingSetting::firstOrCreate([], [
            'show_pricing_section'     => true,
            'pricing_intro_heading'    => 'Smart Retail Management for Growing Businesses',
            'pricing_intro_text'       => 'Manage sales, inventory, staff, customers and multiple branches from anywhere. Transparent pricing designed to help you start easily and scale confidently without surprise fees.',
            'whats_included_heading'   => 'What Every Plan Includes',
            'addons_heading'           => 'Optional Add-Ons & Expansions',
            'addons_content'           => "- **Additional Branch Setup**: From ₦75,000 per branch (includes configuration and staff training).\n- **Custom POS Hardware Pack**: High-speed 80mm thermal receipt printer, barcode scanner, and cash drawer available on request.\n- **Dedicated Data Migration**: Zero-downtime migration from your legacy POS / Excel records.\n- **Extra AI Query Credits**: Available in flexible top-up bundles based on your business demand.",
            'commercial_notes_heading' => 'Important Commercial Notes',
            'commercial_notes_content' => "- **First-Year Investment**: Covers full system setup, initial staff onboarding, cloud hosting provisioning, and first-year platform license.\n- **Annual Renewal**: Covers continuous cloud server hosting, automated daily backups, regular software updates, and uninterrupted technical support.\n- **Hardware Agnostic**: Run on your existing PC, laptop, or Android tablet. No proprietary terminal lock-in required.\n- **Custom Integrations**: Enterprise-grade bespoke integrations and custom feature additions are quoted transparently based on scope.",
            'currency_symbol'          => '₦',
        ]);

        // 2. Value Props
        if (PricingValueProp::count() === 0) {
            $valueProps = [
                ['title' => 'SELL SMARTER', 'subtext' => 'Speed up checkouts and eliminate pricing errors.'],
                ['title' => 'MANAGE EASILY', 'subtext' => 'Centralized control across branches, staff and inventory.'],
                ['title' => 'STAY OPERATIONAL', 'subtext' => 'Offline-first resilience with automatic cloud synchronization.'],
                ['title' => 'BUILT FOR GROWTH', 'subtext' => 'Scale seamlessly from a single store to multi-location retail.'],
            ];
            foreach ($valueProps as $index => $prop) {
                PricingValueProp::create([
                    'title'      => $prop['title'],
                    'subtext'    => $prop['subtext'],
                    'sort_order' => $index + 1,
                ]);
            }
        }

        // 3. Shared Included Items
        if (PricingIncludedItem::count() === 0) {
            $included = [
                'Cloud access with secure branch based data separation',
                'Point of sale checkout and inventory management',
                'Full 100% offline checkout with automatic background cloud sync',
                'Multi-device compatibility (PC, Mac, Android tablets, touch registers)',
                'Receipt printing, barcode generation & barcode scanner plug-and-play',
                'Complete Laundry & Dry Cleaning module (garment intake, workflow tracking & pickups)',
                'Automated database backups & SSL encryption',
            ];
            foreach ($included as $index => $item) {
                PricingIncludedItem::create([
                    'item_text'  => $item,
                    'sort_order' => $index + 1,
                ]);
            }
        }

        // 4. Plans & Features & Capabilities
        $plans = [
            [
                'name'             => 'Starter',
                'tagline'          => 'For a single shop that wants a professional cloud POS with room to grow.',
                'first_year_price' => '₦150,000',
                'renewal_price'    => '₦100,000 / year',
                'branches_limit'   => '1 Branch',
                'users_limit'      => 'Up to 5 Staff Users',
                'ai_credits'       => '25 AI credits monthly',
                'support_level'    => 'Standard remote support',
                'is_highlighted'   => false,
                'sort_order'       => 1,
                'features'         => [
                    'Point of Sale (POS) register & barcode scanning',
                    'Real-time stock inventory & low stock alerts',
                    'Debt and customer account management',
                    'Receipt printing & daily sales reports',
                    '100% offline checkout with automatic sync',
                    'Complete Laundry module (garment intake, workflow & pickups)',
                ],
            ],
            [
                'name'             => 'Bronze',
                'tagline'          => 'Ideal for growing retailers managing inventory across up to 3 locations.',
                'first_year_price' => '₦250,000',
                'renewal_price'    => '₦180,000 / year',
                'branches_limit'   => 'Up to 3 Branches',
                'users_limit'      => 'Up to 10 Users per branch',
                'ai_credits'       => '60 AI credits monthly',
                'support_level'    => 'Priority remote support',
                'is_highlighted'   => false,
                'sort_order'       => 2,
                'features'         => [
                    'Everything in Starter',
                    'Multi-branch inventory lookup & transfers',
                    'Omnichannel E-Commerce Sync & API (WooCommerce ready)',
                    'Product variants (sizes, colors, packages)',
                    'Staff performance tracking & shift reports',
                    'Automated reorder level suggestions',
                ],
            ],
            [
                'name'             => 'Silver',
                'tagline'          => 'Designed for established businesses scaling across multiple retail outlets.',
                'first_year_price' => '₦450,000',
                'renewal_price'    => '₦300,000 / year',
                'branches_limit'   => 'Up to 7 Branches',
                'users_limit'      => 'Up to 20 Users per branch',
                'ai_credits'       => '150 AI credits monthly',
                'support_level'    => 'High-priority remote support & phone',
                'is_highlighted'   => true,
                'sort_order'       => 3,
                'features'         => [
                    'Everything in Bronze',
                    'Automated Double-Entry Accounting & General Ledger',
                    'Balance Sheet, P&L, Trial Balance & VAT Ledger',
                    'E-Commerce Store Sync & Real-Time Stock Reservations',
                    'Centralized purchase order & supplier dispatch',
                    'Advanced discount & promotion matrix',
                    'IMEI / Serial number tracking for gadgets & electronics',
                ],
            ],
            [
                'name'             => 'Gold',
                'tagline'          => 'Comprehensive retail engine for high-volume retail chains & supermarkets.',
                'first_year_price' => '₦700,000',
                'renewal_price'    => '₦450,000 / year',
                'branches_limit'   => 'Up to 15 Branches',
                'users_limit'      => 'Up to 50 Users per branch',
                'ai_credits'       => '400 AI credits monthly',
                'support_level'    => 'Dedicated account manager & SLA support',
                'is_highlighted'   => false,
                'sort_order'       => 4,
                'features'         => [
                    'Everything in Silver',
                    'Full Accounting Engine with Financial Period Lock Controls',
                    'Multi-Store E-Commerce Sync & Omnichannel Allocation',
                    'Enterprise multi-warehouse distribution',
                    'Full Ask-Your-Business AI Copilot analytics',
                    'Custom receipt templates & brand styling',
                    'Comprehensive audit logs & security access control',
                    'Priority onboarding & on-site staff training',
                ],
            ],
            [
                'name'             => 'Enterprise',
                'tagline'          => 'Custom architecture, unlimited scale, and tailored integrations for retail conglomerates.',
                'first_year_price' => 'From ₦1,000,000',
                'renewal_price'    => 'Custom based on deployment',
                'branches_limit'   => '15 included, with expansion available',
                'users_limit'      => 'Unlimited Users',
                'ai_credits'       => 'Advanced AI credit allocation based on agreed usage',
                'support_level'    => '24/7 dedicated support & custom SLA',
                'is_highlighted'   => false,
                'sort_order'       => 5,
                'features'         => [
                    'Everything in Gold',
                    'Custom database deployment & private cloud hosting',
                    'Custom Accounting & ERP Integrations (QuickBooks, SAP, etc.)',
                    'High-Throughput Omnichannel E-Commerce Sync Engine',
                    'Dedicated technical account engineer',
                    'Custom workflow development & bespoke reporting',
                    'Quarterly business review & capacity planning',
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $features = $planData['features'];
            unset($planData['features']);

            $plan = PricingPlan::firstOrCreate(
                ['name' => $planData['name']],
                $planData
            );

            foreach ($features as $fIndex => $fText) {
                PricingPlanFeature::firstOrCreate([
                    'pricing_plan_id' => $plan->id,
                    'feature_text'    => $fText,
                ], [
                    'sort_order' => $fIndex + 1,
                ]);
            }

            $name = strtolower($plan->name);
            $poolType = ($name === 'starter' || $name === 'enterprise') ? 'account_wide' : 'per_branch';

            $monthlyCredits = match ($name) {
                'starter'    => 25,
                'bronze'     => 60,
                'silver'     => 150,
                'gold'       => 400,
                'enterprise' => null,
                default      => 50,
            };

            $maxBranches = match ($name) {
                'starter'    => 1,
                'bronze'     => 3,
                'silver'     => 7,
                'gold'       => 15,
                'enterprise' => null,
                default      => null,
            };

            $maxUsers = match ($name) {
                'starter'    => 5,
                'bronze'     => 10,
                'silver'     => 20,
                'gold'       => 50,
                'enterprise' => null,
                default      => null,
            };

            PlanCapability::updateOrCreate(
                ['pricing_plan_id' => $plan->id],
                [
                    'max_branches'                       => $maxBranches,
                    'max_users_per_branch'               => $maxUsers,
                    'feature_multi_branch'               => $maxBranches !== 1,
                    'feature_debt_customer_accounts'     => $name !== 'starter',
                    'feature_discounts'                  => $name !== 'starter',
                    'feature_batch_expiry_tracking'      => in_array($name, ['silver', 'gold', 'enterprise']),
                    'feature_imei_tracking'              => in_array($name, ['silver', 'gold', 'enterprise']),
                    'feature_barcode_label_printing'     => in_array($name, ['silver', 'gold', 'enterprise']),
                    'feature_inventory_import'           => in_array($name, ['silver', 'gold', 'enterprise']),
                    'feature_advanced_staff_permissions' => in_array($name, ['silver', 'gold', 'enterprise']),
                    'feature_ai_chat_assistant'          => in_array($name, ['bronze', 'silver', 'gold', 'enterprise']),
                    'feature_ai_reorder_prediction'      => in_array($name, ['bronze', 'silver', 'gold', 'enterprise']),
                    'feature_broadcast_notifications'    => in_array($name, ['bronze', 'silver', 'gold', 'enterprise']),
                    'feature_ecommerce_integration'      => in_array($name, ['bronze', 'silver', 'gold', 'enterprise']),
                    'feature_accounting'                 => in_array($name, ['silver', 'gold', 'enterprise']),
                    'ai_credit_pool_type'                => $poolType,
                    'ai_credits_monthly'                 => $monthlyCredits,
                ]
            );
        }
    }
}
