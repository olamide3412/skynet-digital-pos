<?php

namespace Database\Seeders;

use App\Models\PosCustomer;
use Illuminate\Database\Seeder;

class PosCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['name'=>'Emeka Okafor',    'phone'=>'08011111111','gender'=>'Male',  'address'=>'12 Liberty Ave, Abuja'],
            ['name'=>'Amina Bello',     'phone'=>'08022222222','gender'=>'Female','address'=>'5 Ring Rd, Kano'],
            ['name'=>'Chidi Nwosu',     'phone'=>'08033333333','gender'=>'Male',  'address'=>'23 Creek Rd, PH'],
            ['name'=>'Fatima Sule',     'phone'=>'08044444444','gender'=>'Female','address'=>'8 Ahmadu Way, Kaduna'],
            ['name'=>'Tunde Adeyemi',   'phone'=>'08055555555','gender'=>'Male',  'address'=>'14 Allen Ave, Lagos'],
            ['name'=>'Ngozi Ikenna',    'phone'=>'08066666666','gender'=>'Female','address'=>'3 GRA, Enugu'],
            ['name'=>'Bashir Musa',     'phone'=>'08077777777','gender'=>'Male',  'address'=>'7 Bompai Rd, Kano'],
            ['name'=>'Kemi Adebayo',    'phone'=>'08088888888','gender'=>'Female','address'=>'21 Ikorodu Rd, Lagos'],
            ['name'=>'Sunday Obi',      'phone'=>'08099999999','gender'=>'Male',  'address'=>'4 New Market, Aba'],
            ['name'=>'Hauwa Ibrahim',   'phone'=>'07011111111','gender'=>'Female','address'=>'10 Wuse Zone 4, Abuja'],
        ];

        foreach ($customers as $customer) {
            PosCustomer::updateOrCreate(
                ['phone' => $customer['phone']],
                $customer
            );
        }
    }
}
