<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\GroupAddress;
use Illuminate\Database\Seeder;

class PosItemSeeder extends Seeder
{
    public function run(): void
    {
        // Categories
        $cats = [
            ['name' => 'Beverages', 'slug' => 'beverages'],
            ['name' => 'Snacks',    'slug' => 'snacks'],
            ['name' => 'Medicines', 'slug' => 'medicines'],
            ['name' => 'Toiletries','slug' => 'toiletries'],
            ['name' => 'Electronics','slug' => 'electronics'],
        ];
        foreach ($cats as $cat) {
            Category::updateOrCreate(['slug' => $cat['slug']], ['name' => $cat['name'], 'slug' => $cat['slug']]);
        }

        $beverages   = Category::where('slug', 'beverages')->first();
        $snacks      = Category::where('slug', 'snacks')->first();
        $medicines   = Category::where('slug', 'medicines')->first();
        $toiletries  = Category::where('slug', 'toiletries')->first();
        $electronics = Category::where('slug', 'electronics')->first();

        // Group address
        $group = GroupAddress::firstOrCreate(['name' => 'Main Warehouse']);

        // Sample items
        $items = [
            ['item_name'=>'Coca Cola 50cl',       'barcode_number'=>'000001','qty'=>150,'buy_price'=>150,'price'=>200,'wholesale_price'=>180,'category_id'=>$beverages->id],
            ['item_name'=>'Pepsi 50cl',            'barcode_number'=>'000002','qty'=>120,'buy_price'=>140,'price'=>190,'wholesale_price'=>170,'category_id'=>$beverages->id],
            ['item_name'=>'Maltina 60cl',          'barcode_number'=>'000003','qty'=>80, 'buy_price'=>200,'price'=>280,'wholesale_price'=>250,'category_id'=>$beverages->id],
            ['item_name'=>'Pringles Original',     'barcode_number'=>'000004','qty'=>60, 'buy_price'=>500,'price'=>700,'wholesale_price'=>630,'category_id'=>$snacks->id],
            ['item_name'=>'Digestive Biscuits',    'barcode_number'=>'000005','qty'=>45, 'buy_price'=>300,'price'=>420,'wholesale_price'=>380,'category_id'=>$snacks->id],
            ['item_name'=>'Paracetamol 500mg',     'barcode_number'=>'000006','qty'=>200,'buy_price'=>50, 'price'=>80, 'wholesale_price'=>70, 'category_id'=>$medicines->id,'expiry_date'=>'2027-06-30'],
            ['item_name'=>'Vitamin C 1000mg',      'barcode_number'=>'000007','qty'=>150,'buy_price'=>120,'price'=>180,'wholesale_price'=>160,'category_id'=>$medicines->id,'expiry_date'=>'2027-12-31'],
            ['item_name'=>'Amoxicillin 250mg',     'barcode_number'=>'000008','qty'=>100,'buy_price'=>300,'price'=>500,'wholesale_price'=>450,'category_id'=>$medicines->id,'expiry_date'=>'2026-09-30'],
            ['item_name'=>'Dettol Soap',           'barcode_number'=>'000009','qty'=>75, 'buy_price'=>180,'price'=>250,'wholesale_price'=>220,'category_id'=>$toiletries->id],
            ['item_name'=>'Colgate Toothpaste',    'barcode_number'=>'000010','qty'=>90, 'buy_price'=>350,'price'=>480,'wholesale_price'=>430,'category_id'=>$toiletries->id],
            ['item_name'=>'Vaseline 100ml',        'barcode_number'=>'000011','qty'=>60, 'buy_price'=>250,'price'=>380,'wholesale_price'=>340,'category_id'=>$toiletries->id],
            ['item_name'=>'USB-C Cable 1m',        'barcode_number'=>'000012','qty'=>40, 'buy_price'=>800,'price'=>1500,'wholesale_price'=>1300,'category_id'=>$electronics->id],
            ['item_name'=>'Power Bank 10000mAh',   'barcode_number'=>'000013','qty'=>20, 'buy_price'=>5000,'price'=>8500,'wholesale_price'=>7800,'category_id'=>$electronics->id],
            ['item_name'=>'Earbuds Wireless',      'barcode_number'=>'000014','qty'=>15, 'buy_price'=>3500,'price'=>6000,'wholesale_price'=>5500,'category_id'=>$electronics->id],
            ['item_name'=>'Indomie Noodles',       'barcode_number'=>'000015','qty'=>300,'buy_price'=>120,'price'=>175,'wholesale_price'=>155,'category_id'=>$snacks->id],
            ['item_name'=>'Golden Morn Cereal',    'barcode_number'=>'000016','qty'=>50, 'buy_price'=>800,'price'=>1100,'wholesale_price'=>1000,'category_id'=>$snacks->id],
            ['item_name'=>'Malta Guinness 33cl',   'barcode_number'=>'000017','qty'=>100,'buy_price'=>160,'price'=>220,'wholesale_price'=>200,'category_id'=>$beverages->id],
            ['item_name'=>'Ibuprofen 400mg',       'barcode_number'=>'000018','qty'=>120,'buy_price'=>60, 'price'=>100,'wholesale_price'=>85, 'category_id'=>$medicines->id,'expiry_date'=>'2027-03-31'],
            ['item_name'=>'Oral-B Toothbrush',     'barcode_number'=>'000019','qty'=>80, 'buy_price'=>400,'price'=>600,'wholesale_price'=>550,'category_id'=>$toiletries->id],
            ['item_name'=>'Phone Screen Protector','barcode_number'=>'000020','qty'=>35, 'buy_price'=>200,'price'=>500,'wholesale_price'=>450,'category_id'=>$electronics->id],
        ];

        foreach ($items as $itemData) {
            $itemData['group_address_id'] = $group->id;
            Item::updateOrCreate(
                ['barcode_number' => $itemData['barcode_number']],
                $itemData
            );
        }
    }
}
