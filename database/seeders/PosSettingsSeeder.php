<?php

namespace Database\Seeders;

use App\Models\PosSettings;
use Illuminate\Database\Seeder;

class PosSettingsSeeder extends Seeder
{
    public function run(): void
    {
        PosSettings::updateOrCreate([], [
            'is_price_editable'        => false,
            'is_qty_deduction'         => true,
            'out_of_stock'             => 25,
            'is_check_expiration'      => true,
            'is_show_buy_price'        => false,
            'business_name'            => 'SkyNet POS',
            'business_address'         => '1 Commerce Road, Lagos, Nigeria',
            'business_contact_number'  => '+234 800 000 0000',
            'business_email'           => 'admin@skynetpos.com',
            'item_icon_preview'        => false,
            'wholesale_profit_percent' => 10.00,
            'consumer_profit_percent'  => 15.00,
            'sell_interface'           => 'classic',
            'business_sector'          => 'commerce',
        ]);
    }
}
