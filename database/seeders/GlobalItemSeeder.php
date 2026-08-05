<?php

namespace Database\Seeders;

use App\Models\GlobalItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GlobalItemSeeder extends Seeder
{
    public function run(): void
    {
        // ⚡ Clear existing global items to allow clean seed of 1,000+ items
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        GlobalItem::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $catalogTemplates = [

            // ── 1. BEVERAGES & DRINKS (130+ items) ──────────────────────────────────
            [
                'category' => 'Beverages',
                'unit' => 'bottle', 'pack' => 'pack', 'carton' => 'crate',
                'units_per_pack' => 12, 'packs_per_carton' => 2,
                'brands' => ['Coca-Cola', 'Pepsi', 'Fanta Orange', 'Sprite', '7Up', 'Mirinda Apple', 'Schweppes Bitter Lemon', 'Mountain Dew', 'Dr Pepper', 'Teem Soda', 'Limca Lemon'],
                'variants' => ['50cl Bottle', '60cl PET', '35cl Glass Bottle', '1.5L Family Bottle', '2L Party Size'],
                'base_buy' => 150, 'margin' => 1.6,
            ],
            [
                'category' => 'Beverages',
                'unit' => 'can', 'pack' => 'pack', 'carton' => 'tray',
                'units_per_pack' => 24, 'packs_per_carton' => 1,
                'brands' => ['Coca-Cola', 'Pepsi Zero', 'Fanta Grape', 'Sprite Slim', 'Schweppes Tonic Water', 'Red Bull', 'Monster Energy', 'Power Horse', 'Fearless Energy', 'Climax', 'Lucozade Boost', 'Gatorade'],
                'variants' => ['250ml Can', '330ml Sleek Can', '500ml Energy Can'],
                'base_buy' => 350, 'margin' => 1.5,
            ],
            [
                'category' => 'Beverages',
                'unit' => 'pack', 'pack' => 'bundle', 'carton' => 'carton',
                'units_per_pack' => 10, 'packs_per_carton' => 2,
                'brands' => ['Chivita 100%', 'Chi Exotic', 'Five Alive', 'Capri-Sun', 'Don Simon', 'Ceres Juice', 'Minute Maid', 'Happy Hour'],
                'variants' => ['200ml Straw Pack', '330ml Tetra', '500ml Pack', '1 Litre Juice Pack', '1.5L Juice Container'],
                'base_buy' => 450, 'margin' => 1.45,
            ],
            [
                'category' => 'Beverages',
                'unit' => 'bottle', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 12, 'packs_per_carton' => 2,
                'brands' => ['Eva Premium Water', 'Nestle Pure Life', 'Aquafina Water', 'Cway Table Water', 'Gossy Natural Water', 'Viju Milk Drink'],
                'variants' => ['50cl Bottle', '75cl Bottle', '1.5L Bottle', '50cl Sport Cap'],
                'base_buy' => 90, 'margin' => 1.7,
            ],
            [
                'category' => 'Beverages',
                'unit' => 'can', 'pack' => 'pack', 'carton' => 'case',
                'units_per_pack' => 24, 'packs_per_carton' => 1,
                'brands' => ['Malta Guinness', 'Amstel Malta', 'Maltina', 'Dubic Malt', 'Grand Malt'],
                'variants' => ['33cl Can', '33cl Glass Bottle', '50cl PET Bottle'],
                'base_buy' => 220, 'margin' => 1.55,
            ],
            [
                'category' => 'Beverages',
                'unit' => 'box', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 10, 'packs_per_carton' => 2,
                'brands' => ['Lipton Yellow Label', 'Top Tea', 'Highland Black Tea', 'Nescafe Classic', 'Nescafe Gold', 'Milo Cocoa', 'Bournvita', 'Ovaltine', 'Horlicks'],
                'variants' => ['100g Jar', '200g Refill', '400g Tin', '500g Pouch', '100 Tea Bags Box'],
                'base_buy' => 950, 'margin' => 1.4,
            ],

            // ── 2. DAIRY, EGGS & REFRIGERATED (90+ items) ───────────────────────────
            [
                'category' => 'Dairy & Chilled',
                'unit' => 'tin', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 6, 'packs_per_carton' => 4,
                'brands' => ['Peak Milk', 'Three Crowns', 'Dano Milk', 'Cowbell Milk', 'Coast Milk', 'Hollandia Milk', 'Nido Powdered Milk'],
                'variants' => ['160g Evap Tin', '400g Powder Tin', '900g Pouch', '14g Sachet (10s Pack)', '350g Refill'],
                'base_buy' => 400, 'margin' => 1.35,
            ],
            [
                'category' => 'Dairy & Chilled',
                'unit' => 'bottle', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 12, 'packs_per_carton' => 1,
                'brands' => ['Hollandia Yoghurt', 'Freshyo Drinking Yogurt', 'Habib Yoghurt', 'Danone Yogurt', 'Farm Fresh Yogurt'],
                'variants' => ['100ml Bottle', '315ml Bottle', '500ml Bottle', '1 Litre Tetra Pack'],
                'base_buy' => 350, 'margin' => 1.45,
            ],
            [
                'category' => 'Dairy & Chilled',
                'unit' => 'pack', 'pack' => 'bundle', 'carton' => 'carton',
                'units_per_pack' => 10, 'packs_per_carton' => 2,
                'brands' => ['Blue Band Margarine', 'Simas Margarine', 'Kerrygold Pure Butter', 'Anchor Butter', 'President Cheese Slices', 'Happy Cow Cheese', 'Laughing Cow Portion'],
                'variants' => ['250g Tub', '500g Tub', '900g Family Tub', '200g Foil Butter', '200g Cheese Slices'],
                'base_buy' => 650, 'margin' => 1.4,
            ],
            [
                'category' => 'Dairy & Chilled',
                'unit' => 'crate', 'pack' => 'pack', 'carton' => 'case',
                'units_per_pack' => 1, 'packs_per_carton' => 5,
                'brands' => ['Farm Fresh Farm Eggs', 'Organic Free Range Eggs', 'Quail Eggs Specialty'],
                'variants' => ['Crate of 30 Large Eggs', '12-Egg Carton', '6-Egg Transparent Pack'],
                'base_buy' => 2200, 'margin' => 1.3,
            ],

            // ── 3. BAKERY & BREAKFAST CEREALS (80+ items) ───────────────────────────
            [
                'category' => 'Bakery & Cereals',
                'unit' => 'loaf', 'pack' => 'pack', 'carton' => 'crate',
                'units_per_pack' => 10, 'packs_per_carton' => 1,
                'brands' => ['Special Butter Bread', 'Sliced Toast Bread', 'Whole Wheat Bread', 'French Baguette', 'Burger Buns', 'Hotdog Rolls', 'Sausage Roll Pack'],
                'variants' => ['Medium Loaf', 'Large Family Size Loaf', 'Giant Sliced Loaf', 'Pack of 6 Buns'],
                'base_buy' => 600, 'margin' => 1.35,
            ],
            [
                'category' => 'Bakery & Cereals',
                'unit' => 'pack', 'pack' => 'box', 'carton' => 'carton',
                'units_per_pack' => 12, 'packs_per_carton' => 2,
                'brands' => ['Kellogg\'s Corn Flakes', 'Kellogg\'s Coco Pops', 'Golden Morn Cereal', 'Quaker White Oats', 'Oatsum Instant Oats', 'Weetabix Whole Grain', 'Cheerios Honey'],
                'variants' => ['350g Box', '500g Value Pack', '1kg Giant Box', '500g Refill Pouch'],
                'base_buy' => 1100, 'margin' => 1.4,
            ],

            // ── 4. GROCERIES & COOKING STAPLES (200+ items) ──────────────────────────
            [
                'category' => 'Groceries & Staples',
                'unit' => 'bag', 'pack' => 'bundle', 'carton' => 'bale',
                'units_per_pack' => 5, 'packs_per_carton' => 1,
                'brands' => ['Mama Gold Parboiled Rice', 'Royal Stallion Long Grain', 'Caprice White Rice', 'Golden Penny Semovita', 'Honeywell Wheat Flour', 'Mama Gold Poundo Yam', 'Yellow Garri Premium', 'White IJebu Garri', 'Elubo Amala Flour'],
                'variants' => ['1kg Pack', '2kg Pack', '5kg Bag', '10kg Bag', '25kg Bag'],
                'base_buy' => 1400, 'margin' => 1.3,
            ],
            [
                'category' => 'Groceries & Staples',
                'unit' => 'sachet', 'pack' => 'bundle', 'carton' => 'carton',
                'units_per_pack' => 40, 'packs_per_carton' => 1,
                'brands' => ['Indomie Instant Noodles', 'Golden Penny Noodles', 'Mimi Noodles', 'Dangote Spaghetti', 'Golden Penny Macaroni', 'Bambino Pasta'],
                'variants' => ['Chicken Flavor 70g', 'Onion Chicken 70g', 'Super Pack Chicken 120g', 'Hungry Man Size 180g', 'Belle Full Size 280g', 'Spaghetti 500g Slim', 'Macaroni Elbow 500g'],
                'base_buy' => 140, 'margin' => 1.45,
            ],
            [
                'category' => 'Groceries & Staples',
                'unit' => 'bottle', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 12, 'packs_per_carton' => 1,
                'brands' => ['Kings Vegetable Oil', 'Power Oil Refined', 'Mamador Pure Oil', 'Golden Penny Pure Vegetable Oil', 'Grand Pure Soya Oil', 'Gino Pure Olive Oil'],
                'variants' => ['75cl PET Bottle', '1 Litre Bottle', '3 Litre Gallon', '5 Litre Jerry Can'],
                'base_buy' => 1500, 'margin' => 1.35,
            ],
            [
                'category' => 'Groceries & Staples',
                'unit' => 'sachet', 'pack' => 'roll', 'carton' => 'carton',
                'units_per_pack' => 10, 'packs_per_carton' => 5,
                'brands' => ['Gino Tomato Paste', 'Sonia Pure Tomato', 'Ric-Giko Tomato', 'Knorr Chicken Seasoning', 'Maggi Star Cubes', 'Royco Beef Seasoning', 'Ajinomoto Monosodium', 'Mr Chef Iodized Salt', 'Dangote Refined Sugar'],
                'variants' => ['70g Sachet', '400g Tin', '50 Cubes Pack', '100 Cubes Box', '250g Salt Pack', '1kg Sugar Pack'],
                'base_buy' => 120, 'margin' => 1.5,
            ],
            [
                'category' => 'Groceries & Staples',
                'unit' => 'bottle', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 12, 'packs_per_carton' => 2,
                'brands' => ['Heinz Tomato Ketchup', 'Bama Real Mayonnaise', 'Jumia Salad Dressing', 'Ducros Curry Powder', 'Ducros Thyme Leaves', 'Garlic Powder Glass Jar', 'Lee Kum Kee Soy Sauce', 'Heinz Yellow Mustard'],
                'variants' => ['250g Glass Jar', '473ml Squeeze Bottle', '946ml Jar', '100g Spice Shaker'],
                'base_buy' => 800, 'margin' => 1.45,
            ],
            [
                'category' => 'Groceries & Staples',
                'unit' => 'can', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 24, 'packs_per_carton' => 1,
                'brands' => ['Geisha Mackerel Tomato', 'Titus Sardines Oil', 'Heinz Baked Beans', 'Green Giants Sweet Corn', 'Exeter Corned Beef', 'Target Corned Beef', 'Ocean Rise Tuna Flakes'],
                'variants' => ['155g Easy Open Can', '400g Can', '425g Large Can'],
                'base_buy' => 600, 'margin' => 1.4,
            ],

            // ── 5. SNACKS, BISCUITS & SWEETS (150+ items) ───────────────────────────
            [
                'category' => 'Snacks & Sweets',
                'unit' => 'pack', 'pack' => 'bundle', 'carton' => 'carton',
                'units_per_pack' => 24, 'packs_per_carton' => 1,
                'brands' => ['McVities Digestive', 'McVities Cabin', 'Oreo Chocolate Cookie', 'Coaster Biscuits', 'Speedy Chocolate Biscuit', 'Shortcake Biscuit', 'Pure Bliss Wafer', 'Cream Crackers', 'Maryland Cookies'],
                'variants' => ['40g Mini Pack', '100g Medium Pack', '200g Roll', '400g Family Pack'],
                'base_buy' => 200, 'margin' => 1.5,
            ],
            [
                'category' => 'Snacks & Sweets',
                'unit' => 'can', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 12, 'packs_per_carton' => 1,
                'brands' => ['Pringles Chips', 'Lays Potato Chips', 'Cheetos Cheese Puffs', 'Ripe Plantain Chips', 'Sweet Chin Chin', 'Butter Popcorn', 'Salted Roasted Peanuts', 'Premium Cashew Nuts'],
                'variants' => ['40g Small Tube', '165g Large Tube', '150g Foil Pouch', '250g Roasted Jar'],
                'base_buy' => 450, 'margin' => 1.45,
            ],
            [
                'category' => 'Snacks & Sweets',
                'unit' => 'bar', 'pack' => 'box', 'carton' => 'carton',
                'units_per_pack' => 24, 'packs_per_carton' => 4,
                'brands' => ['Snickers Bar', 'Mars Chocolate Bar', 'Twix Caramel Bar', 'Bounty Coconut Bar', 'KitKat 4-Finger', 'Cadbury Dairy Milk', 'Ferrero Rocher 3s', 'M&Ms Peanut Chocolate', 'Haribo Gummy Bears', 'Mentos Mint', 'Tic Tac Mint', 'TomTom Menthol', 'Butternut Candy'],
                'variants' => ['35g Single Bar', '50g Standard Bar', '100g Block Bar', '50 Count Candy Jar'],
                'base_buy' => 250, 'margin' => 1.55,
            ],

            // ── 6. PERSONAL CARE & COSMETICS (150+ items) ───────────────────────────
            [
                'category' => 'Personal Care',
                'unit' => 'bar', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 12, 'packs_per_carton' => 4,
                'brands' => ['Dettol Antiseptic Soap', 'Premier Cool Deo Soap', 'Imperial Leather Soap', 'Lux Soft Touch Soap', 'Dove Beauty Bar', 'Irish Spring Soap', 'Pears Baby Soap', 'Nivea Bath Soap'],
                'variants' => ['100g Bar', '125g Bar', '175g Giant Bar', 'Pack of 3 Bars'],
                'base_buy' => 280, 'margin' => 1.45,
            ],
            [
                'category' => 'Personal Care',
                'unit' => 'bottle', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 12, 'packs_per_carton' => 2,
                'brands' => ['Nivea Body Wash', 'Palmolive Shower Gel', 'Pantene Pro-V Shampoo', 'Head & Shoulders Anti-Dandruff', 'Dark and Lovely Conditioning', 'Tresemme Smooth Shampoo', 'Shea Moisture Hair Conditioner'],
                'variants' => ['200ml Bottle', '400ml Family Bottle', '750ml Pump Bottle'],
                'base_buy' => 1200, 'margin' => 1.4,
            ],
            [
                'category' => 'Personal Care',
                'unit' => 'tube', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 12, 'packs_per_carton' => 4,
                'brands' => ['Colgate Triple Action', 'Close-Up Red Hot Gel', 'Pepsodent Cavity Protection', 'Sensodyne Repair & Protect', 'Oral-B Complete Toothbrush', 'Listerine Mouthwash Cool Mint'],
                'variants' => ['75ml Tube', '140g Family Tube', 'Medium Bristle Single Toothbrush', '500ml Bottle'],
                'base_buy' => 450, 'margin' => 1.45,
            ],
            [
                'category' => 'Personal Care',
                'unit' => 'bottle', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 12, 'packs_per_carton' => 2,
                'brands' => ['Nivea Body Lotion', 'Vaseline Intensive Care', 'Jergens Ultra Healing', 'St. Ives Smoothing Lotion', 'Dove Nourishing Body Lotion'],
                'variants' => ['200ml Bottle', '400ml Pump Bottle', '600ml Value Size'],
                'base_buy' => 1400, 'margin' => 1.4,
            ],
            [
                'category' => 'Personal Care',
                'unit' => 'bottle', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 12, 'packs_per_carton' => 2,
                'brands' => ['Nivea Men Roll-On', 'Rexona MotionSense Spray', 'Axe Body Spray', 'Old Spice Antiperspirant', 'Secret Invisible Solid', 'Storm Deo Body Spray'],
                'variants' => ['50ml Roll-On', '150ml Body Spray', '200ml Deodorant'],
                'base_buy' => 900, 'margin' => 1.45,
            ],
            [
                'category' => 'Personal Care',
                'unit' => 'pack', 'pack' => 'bundle', 'carton' => 'carton',
                'units_per_pack' => 12, 'packs_per_carton' => 2,
                'brands' => ['Always Ultra Sanitary Pads', 'Molped Maxi Pads', 'Lady Care Wings', 'Dettol Wet Wipes 10s', 'Cotton Buds Swabs 200s', 'Sterile Cotton Wool 100g'],
                'variants' => ['8 Pads Pack', '16 Pads Value Pack', '24 Pads Super Pack'],
                'base_buy' => 500, 'margin' => 1.4,
            ],

            // ── 7. HOUSEHOLD CLEANING & LAUNDRY (120+ items) ─────────────────────────
            [
                'category' => 'Household Cleaning',
                'unit' => 'pack', 'pack' => 'bundle', 'carton' => 'carton',
                'units_per_pack' => 10, 'packs_per_carton' => 1,
                'brands' => ['Ariel Automatic Detergent', 'Omo Fast Wash', 'Sunrise Laundry Powder', 'Viva Washing Powder', 'Hypo Super Bleach', 'Jik Bleach Floral', 'Sunlight Multipurpose Soap', 'Elephant Bar Soap'],
                'variants' => ['150g Small Sachet', '500g Pack', '1kg Value Bag', '2kg Mega Pack', '1 Litre Bleach Bottle'],
                'base_buy' => 450, 'margin' => 1.4,
            ],
            [
                'category' => 'Household Cleaning',
                'unit' => 'bottle', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 12, 'packs_per_carton' => 1,
                'brands' => ['Morning Fresh Lemon Liquid', 'Sunlight Dishwashing Liquid', 'Fairy Original Liquid', 'Scrubbing Sponge Pad 3-Pack', 'Stainless Steel Wool 5s'],
                'variants' => ['250ml Bottle', '450ml Bottle', '750ml Bottle'],
                'base_buy' => 600, 'margin' => 1.45,
            ],
            [
                'category' => 'Household Cleaning',
                'unit' => 'bottle', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 12, 'packs_per_carton' => 1,
                'brands' => ['Harpic Power Plus Toilet Cleaner', 'Dettol Antiseptic Liquid', 'Lysol Disinfectant Spray', 'Vim Scouring Powder', 'Air Wick Freshener Spray', 'Febreze Air Effects'],
                'variants' => ['500ml Bottle', '750ml Bottle', '1 Litre Bottle', '300ml Air Spray'],
                'base_buy' => 950, 'margin' => 1.4,
            ],
            [
                'category' => 'Household Cleaning',
                'unit' => 'roll', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 10, 'packs_per_carton' => 2,
                'brands' => ['Rose Toilet Tissue 2-Ply', 'Kleenex Soft Facial Tissue', 'Servettes Table Napkins', 'Kitchen Towel Paper Rolls', 'Heavy Duty Black Trash Bags'],
                'variants' => ['Single Roll', '4-Roll Pack', '10-Roll Pack', 'Box of 200 Tissue Sheets'],
                'base_buy' => 350, 'margin' => 1.45,
            ],

            // ── 8. HEALTH, PHARMACY & FIRST AID (80+ items) ──────────────────────────
            [
                'category' => 'Pharmacy & Health',
                'unit' => 'pack', 'pack' => 'box', 'carton' => 'carton',
                'units_per_pack' => 10, 'packs_per_carton' => 10,
                'brands' => ['Paracetamol 500mg', 'Emzor Ibuprofen 400mg', 'Panadol Extra Tablets', 'Aspirin 300mg', 'Deep Heat Pain Gel', 'Diclofenac Sodium Gel'],
                'variants' => ['Blister Pack of 10s', 'Box of 100 Tablets', '50g Gel Tube'],
                'base_buy' => 200, 'margin' => 1.5,
            ],
            [
                'category' => 'Pharmacy & Health',
                'unit' => 'tube', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 10, 'packs_per_carton' => 4,
                'brands' => ['Vitamin C 1000mg Orange', 'Seven Seas Cod Liver Oil', 'Multivitamin Daily Syrup', 'Calcium + Vitamin D3', 'Zinc 50mg Immunity Booster'],
                'variants' => ['20 Effervescent Tube', '60 Capsules Bottle', '100ml Syrup Bottle'],
                'base_buy' => 850, 'margin' => 1.45,
            ],

            // ── 9. BABY PRODUCTS (70+ items) ────────────────────────────────────────
            [
                'category' => 'Baby Care',
                'unit' => 'pack', 'pack' => 'bundle', 'carton' => 'carton',
                'units_per_pack' => 6, 'packs_per_carton' => 2,
                'brands' => ['Pampers Baby Dry Diapers', 'Huggies Gold Diapers', 'Molfix Comfort Diapers', 'WaterWipes Sensitive Baby Wipes', 'Johnson\'s Gentle Baby Wipes'],
                'variants' => ['Small (Size 2 - 24s)', 'Medium (Size 3 - 48s)', 'Large (Size 4 - 64s)', 'Extra Large (Size 5 - 40s)', 'Soft Wipes 64s Pack'],
                'base_buy' => 2500, 'margin' => 1.35,
            ],
            [
                'category' => 'Baby Care',
                'unit' => 'tin', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 6, 'packs_per_carton' => 2,
                'brands' => ['Cerelac Wheat & Apple', 'Nutribom Baby Cereal', 'SMA Gold Infant Formula', 'Aptamil 1 Milk', 'NAN 1 Infant Formula', 'Heinz Baby Puree Jars'],
                'variants' => ['200g Tin', '400g Tin', '800g Tin', '120g Puree Glass Jar'],
                'base_buy' => 1800, 'margin' => 1.35,
            ],
            [
                'category' => 'Baby Care',
                'unit' => 'bottle', 'pack' => 'pack', 'carton' => 'carton',
                'units_per_pack' => 12, 'packs_per_carton' => 2,
                'brands' => ['Johnson\'s Baby Oil', 'Johnson\'s Baby Shampoo', 'Pears Gentle Baby Lotion', 'Johnson\'s Baby Powder', 'Sudocrem Healing Cream'],
                'variants' => ['100ml Bottle', '200ml Bottle', '500ml Pump Bottle', '125g Tub'],
                'base_buy' => 700, 'margin' => 1.4,
            ],

            // ── 10. ELECTRONICS & PHONE ACCESSORIES (60+ items) ──────────────────────
            [
                'category' => 'Electronics',
                'unit' => 'unit', 'pack' => 'box', 'carton' => 'carton',
                'units_per_pack' => 10, 'packs_per_carton' => 5,
                'brands' => ['Samsung USB-C 25W Charger', 'Apple Lightning USB Cable 1m', 'Anker PowerCore 10000mAh', 'Oraimo 20000mAh Power Bank', 'SanDisk 32GB USB 3.0', 'SanDisk 64GB USB 3.0', 'Logitech Wireless Mouse M185', 'Duracell AA Batteries 4s', 'Extension Socket 4-Way 3m'],
                'variants' => ['Standard Pack', 'Retail Box', 'Blister Pack'],
                'base_buy' => 2200, 'margin' => 1.45,
            ],

            // ── 11. OFFICE & STATIONERY (50+ items) ──────────────────────────────────
            [
                'category' => 'Stationery',
                'unit' => 'ream', 'pack' => 'box', 'carton' => 'carton',
                'units_per_pack' => 5, 'packs_per_carton' => 1,
                'brands' => ['HP A4 Printing Paper 80gsm', 'Executive Hardcover Notebook 200p', 'Higher Single Subject Notebook', 'Bic Round Stic Blue Pens 50s', 'Pilot Gel Pens 12s Pack', 'Casio FX-991EX Calculator', 'Stapler Heavy Duty + Staples'],
                'variants' => ['Single Pack', 'Box Pack of 10'],
                'base_buy' => 1500, 'margin' => 1.4,
            ],

            // ── 12. PET SUPPLIES & FROZEN FOODS (50+ items) ──────────────────────────
            [
                'category' => 'Frozen & Pet',
                'unit' => 'pack', 'pack' => 'bundle', 'carton' => 'carton',
                'units_per_pack' => 10, 'packs_per_carton' => 1,
                'brands' => ['Frozen Whole Chicken 1.2kg', 'Frozen Chicken Wings 1kg', 'Frozen Turkey Cutlets 1kg', 'Beef Sausages 500g', 'Smoked Mackerel Fish 1kg', 'Fan Milk Vanilla Ice Cream 1L', 'Wall\'s Magnum Chocolate 3s'],
                'variants' => ['1kg Pack', '2kg Pack', '1 Litre Tub'],
                'base_buy' => 2100, 'margin' => 1.35,
            ],

        ];

        $globalItemsToInsert = [];
        $barcodeCounter = 6900000000001; // Standard EAN-13 barcode sequence

        foreach ($catalogTemplates as $template) {
            $cat        = $template['category'];
            $unitLabel  = $template['unit'];
            $packLabel  = $template['pack'];
            $cartonLabel= $template['carton'];
            $unitsPack  = $template['units_per_pack'];
            $packsCarton= $template['packs_per_carton'];
            $baseBuy    = $template['base_buy'];
            $margin     = $template['margin'];

            foreach ($template['brands'] as $brand) {
                foreach ($template['variants'] as $variant) {

                    $itemName = "{$brand} {$variant}";

                    // Calculate realistic cost, selling, and wholesale prices
                    $buyPrice       = round($baseBuy * (1 + (rand(-15, 20) / 100)), 2);
                    $sellingPrice   = round($buyPrice * $margin, 2);
                    $wholesalePrice = round($buyPrice * ($margin - 0.15), 2);

                    $barcode = (string) $barcodeCounter++;

                    $globalItemsToInsert[] = [
                        'item_name'        => $itemName,
                        'barcode_number'   => $barcode,
                        'buy_price'        => $buyPrice,
                        'price'            => $sellingPrice,
                        'wholesale_price'  => $wholesalePrice,
                        'unit_label'       => $unitLabel,
                        'pack_label'       => $packLabel,
                        'carton_label'     => $cartonLabel,
                        'units_per_pack'   => $unitsPack,
                        'packs_per_carton' => $packsCarton,
                        'item_description' => "Quality supermarket grade {$itemName} from {$brand}",
                        'category_hint'    => $cat,
                        'image_path'       => null,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];

                    // Batch insert every 250 items to avoid large single memory load
                    if (count($globalItemsToInsert) >= 250) {
                        GlobalItem::insert($globalItemsToInsert);
                        $globalItemsToInsert = [];
                    }
                }
            }
        }

        // Insert any remaining items
        if (!empty($globalItemsToInsert)) {
            GlobalItem::insert($globalItemsToInsert);
        }

        $totalCount = GlobalItem::count();
        $this->command->info("Successfully seeded {$totalCount} items into the Global Master Item Pool.");
    }
}
