<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingSetting extends Model
{
    protected $table = 'pricing_settings';

    protected $fillable = [
        'show_pricing_section',
        'pricing_intro_heading',
        'pricing_intro_text',
        'whats_included_heading',
        'addons_heading',
        'addons_content',
        'commercial_notes_heading',
        'commercial_notes_content',
        'currency_symbol',
    ];

    protected $casts = [
        'show_pricing_section' => 'boolean',
    ];

    public static function current(): self
    {
        return static::firstOrCreate([], [
            'show_pricing_section'     => true,
            'pricing_intro_heading'    => 'Smart Retail Management for Growing Businesses',
            'pricing_intro_text'       => 'Manage sales, inventory, staff, customers and multiple branches from anywhere. Transparent pricing designed to help you start easily and scale confidently without surprise fees.',
            'whats_included_heading'   => 'What Every Plan Includes',
            'addons_heading'           => 'Optional Add-Ons & Expansions',
            'addons_content'           => "- **Additional Branch Setup**: From ₦75,000 per branch (includes full configuration and staff training).\n- **Custom POS Hardware Pack**: High-speed 80mm thermal receipt printer, barcode scanner, and cash drawer available on request.\n- **Dedicated Data Migration**: Zero-downtime migration from your legacy POS / Excel records.\n- **Extra AI Query Credits**: Available in flexible top-up bundles based on your business demand.",
            'commercial_notes_heading' => 'Important Commercial Notes',
            'commercial_notes_content' => "- **First-Year Investment**: Covers full system setup, initial staff onboarding, cloud hosting provisioning, and first-year platform license.\n- **Annual Renewal**: Covers continuous cloud server hosting, automated daily backups, regular software updates, and uninterrupted technical support.\n- **Hardware Agnostic**: Run on your existing PC, laptop, or Android tablet. No proprietary terminal lock-in required.\n- **Custom Integrations**: Enterprise-grade bespoke integrations and custom feature additions are quoted transparently based on scope.",
            'currency_symbol'          => '₦',
        ]);
    }
}
