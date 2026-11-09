<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\DebtPayment;
use App\Models\PosCustomer;
use App\Models\User;
use App\Modules\Laundry\Models\LaundryCategory;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Models\LaundryOrderItem;
use App\Modules\Laundry\Models\LaundryOrderStatusHistory;
use App\Modules\Laundry\Models\LaundryPayment;
use App\Modules\Laundry\Models\LaundryService;
use App\Modules\Laundry\Models\LaundrySetting;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LaundryTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::where('slug', 'test-branch')->first()
            ?: Branch::where('slug', 'monty-laundry')->first()
            ?: Branch::first();

        if (!$branch) {
            $this->command->error('No branch found.');
            return;
        }

        $user = $branch->users()->first() ?? User::first();
        if (!$user) {
            $this->command->error('No user found for branch.');
            return;
        }

        $settings = LaundrySetting::forBranch($branch->id);

        // ── 0. Deep Clean all branch laundry records, debts, and customers ───────
        $existingOrderIds = LaundryOrder::where('branch_id', $branch->id)->pluck('id');
        if ($existingOrderIds->isNotEmpty()) {
            LaundryOrderItem::whereIn('order_id', $existingOrderIds)->delete();
            LaundryOrderStatusHistory::whereIn('order_id', $existingOrderIds)->delete();
            LaundryPayment::whereIn('order_id', $existingOrderIds)->delete();
            LaundryOrder::whereIn('id', $existingOrderIds)->delete();
        }
        LaundryPayment::where('branch_id', $branch->id)->delete();
        DebtPayment::where('branch_id', $branch->id)->delete();
        PosCustomer::where('branch_id', $branch->id)->delete();

        // ── 1. Ensure Categories & Services Exist ───────────────────────────────
        $categoriesMap = [];
        $uniqueCategories = [
            'Dry Cleaning' => 'Suits, formal evening wear, delicate fabrics',
            'Traditional' => 'Native agbada, kaftans, caps and traditional attire',
            'Wash & Fold' => 'Everyday shirts, casual laundry, bulk wash by KG',
            'Ironing' => 'Steam pressing and crisp ironing',
            'Bedding' => 'Duvets, blankets, bedsheets and pillowcases',
            'Household' => 'Curtains, drapes and heavy household fabrics',
        ];

        $sort = 1;
        foreach ($uniqueCategories as $catName => $desc) {
            $cat = LaundryCategory::firstOrCreate(
                ['branch_id' => $branch->id, 'name' => $catName],
                [
                    'description' => $desc,
                    'sort_order' => $sort++,
                    'is_active' => true,
                ]
            );
            $categoriesMap[$catName] = $cat->id;
        }

        $defaultServices = [
            ['name' => 'Suit 2-Piece Dry Clean', 'category' => 'Dry Cleaning', 'pricing_type' => 'per_piece', 'price' => 3500.00],
            ['name' => 'Suit 3-Piece Dry Clean', 'category' => 'Dry Cleaning', 'pricing_type' => 'per_piece', 'price' => 5000.00],
            ['name' => 'Native Agbada Complete', 'category' => 'Traditional', 'pricing_type' => 'per_piece', 'price' => 4000.00],
            ['name' => 'Kaftan / Senator Wear', 'category' => 'Traditional', 'pricing_type' => 'per_piece', 'price' => 2500.00],
            ['name' => 'Office Shirt (Wash & Iron)', 'category' => 'Wash & Fold', 'pricing_type' => 'per_piece', 'price' => 1200.00],
            ['name' => 'Trousers / Chinos Press', 'category' => 'Ironing', 'pricing_type' => 'per_piece', 'price' => 900.00],
            ['name' => 'Evening Gown / Dress', 'category' => 'Dry Cleaning', 'pricing_type' => 'per_piece', 'price' => 4500.00],
            ['name' => 'Duvet / Bedspread (Large)', 'category' => 'Bedding', 'pricing_type' => 'per_piece', 'price' => 6000.00],
            ['name' => 'Bedsheets & Pillowcases', 'category' => 'Bedding', 'pricing_type' => 'per_piece', 'price' => 3000.00],
            ['name' => 'Casual Clothes (Bulk/KG)', 'category' => 'Wash & Fold', 'pricing_type' => 'per_kg', 'price' => 1500.00],
            ['name' => 'Heavy Curtains (Per KG)', 'category' => 'Household', 'pricing_type' => 'per_kg', 'price' => 1800.00],
            ['name' => 'Jeans / Denim Wash', 'category' => 'Wash & Fold', 'pricing_type' => 'per_piece', 'price' => 1400.00],
        ];

        foreach ($defaultServices as $idx => $srv) {
            LaundryService::updateOrCreate(
                ['branch_id' => $branch->id, 'name' => $srv['name']],
                [
                    'category_id' => $categoriesMap[$srv['category']] ?? null,
                    'pricing_type' => $srv['pricing_type'],
                    'price' => $srv['price'],
                    'is_active' => true,
                    'sort_order' => $idx + 1,
                ]
            );
        }

        $services = LaundryService::where('branch_id', $branch->id)->where('is_active', true)->get();

        // ── 2. Seed 25 Fresh Customers ──────────────────────────────────────────
        $customerList = [
            ['name' => 'Chief Adebayo Alabi', 'phone' => '08031234561', 'address' => '15 Admiralty Way, Lekki'],
            ['name' => 'Dr. Ngozi Okonjo', 'phone' => '08023456782', 'address' => '42 Victoria Island, Lagos'],
            ['name' => 'Alhaji Ibrahim Danjuma', 'phone' => '08145678903', 'address' => '8 Gana Street, Maitama, Abuja'],
            ['name' => 'Engr. Femi Otedola', 'phone' => '07056789014', 'address' => '12 Bourdillon Rd, Ikoyi'],
            ['name' => 'Barrister Chioma Eze', 'phone' => '08067890125', 'address' => '7 Wuse Zone 2, Abuja'],
            ['name' => 'Pastor Tunde Bakare', 'phone' => '08178901236', 'address' => '22 Allen Ave, Ikeja'],
            ['name' => 'Hajiya Aisha Bello', 'phone' => '08089012347', 'address' => '19 Bompai Rd, Kano'],
            ['name' => 'Mr. Kenneth Nnamdi', 'phone' => '07090123458', 'address' => '31 New Market Rd, Onitsha'],
            ['name' => 'Mrs. Folake Solanke', 'phone' => '08012345679', 'address' => '14 Bodija, Ibadan'],
            ['name' => 'Capt. Babatunde Raji', 'phone' => '08123456780', 'address' => '5 GRA Phase 2, Port Harcourt'],
            ['name' => 'Hon. Ahmed Lawan', 'phone' => '08034567891', 'address' => '9 Asokoro Hill, Abuja'],
            ['name' => 'Engr. Emeka Nwosu', 'phone' => '08145678902', 'address' => '11 Ogui Rd, Enugu'],
            ['name' => 'Mrs. Zainab Ahmed', 'phone' => '07056789013', 'address' => '26 Ahmadu Bello Way, Kaduna'],
            ['name' => 'Senator Victor Umeh', 'phone' => '08067890124', 'address' => '18 Nza Street, Independence Layout'],
            ['name' => 'Dr. Kemi Olunloyo', 'phone' => '08178901235', 'address' => '3 Maryland Crescent, Lagos'],
            ['name' => 'Mr. Segun Arinze', 'phone' => '08089012346', 'address' => '17 Surulere Way, Lagos'],
            ['name' => 'Mrs. Halima Dangote', 'phone' => '07090123457', 'address' => '5 Bompai Crescent, Kano'],
            ['name' => 'Prof. Wole Soyinka', 'phone' => '08012345688', 'address' => '10 Kemta Estate, Abeokuta'],
            ['name' => 'Mr. Kunle Afolayan', 'phone' => '08123456799', 'address' => '8 Magodo Phase 2, Lagos'],
            ['name' => 'Miss Tiwa Savage', 'phone' => '08034567800', 'address' => '14 Banana Island, Ikoyi'],
            ['name' => 'Alhaja Sinatu Ojikutu', 'phone' => '08022334455', 'address' => '3 Bishop Kale Close, Victoria Island'],
            ['name' => 'Pastor Enoch Adeboye', 'phone' => '08033445566', 'address' => 'Camp Ground, Km 46 Lagos-Ibadan Exp'],
            ['name' => 'Mrs. Ibukun Awosika', 'phone' => '08055667788', 'address' => '21 Olosa Street, Victoria Island'],
            ['name' => 'Mr. Tony Elumelu', 'phone' => '08077889900', 'address' => '1 Marina Street, Lagos Island'],
            ['name' => 'Chief Gabriel Igbinedion', 'phone' => '08099001122', 'address' => '4 Airport Road, GRA, Benin City'],
        ];

        $customers = [];
        foreach ($customerList as $custData) {
            $c = PosCustomer::create([
                'branch_id' => $branch->id,
                'name' => $custData['name'],
                'phone' => $custData['phone'],
                'address' => $custData['address'],
                'whatsapp_number' => $custData['phone'],
                'debt_bal' => 0,
            ]);
            $customers[] = $c;
        }

        $now = Carbon::now();
        $sampleGarmentNotes = [
            'Handle with care, delicate embroidery',
            'Heavy stain removal needed on collar and cuffs',
            'Light starch only please',
            'No starch on collars, soft luxury press',
            'Urgent pickup requested for Saturday wedding',
            'Check for loose buttons on left sleeve',
            'Double press sharp crease on trousers',
            'Separate colors, gentle cold wash cycle',
            'Special dry clean - silk & cashmere fabric',
            'VIP Customer - pack in breathable suit carrier',
            'Customer requested perfume rinse finish',
            '',
            '',
        ];

        $paymentMethods = ['Cash', 'Card / POS', 'Bank Transfer'];

        // ── 3. Build 100 Rich Order Configurations ──────────────────────────────
        // A) OVERDUE (20 orders: due_at in past, status received/in_progress)
        // B) DUE TODAY (20 orders: due_at today, received/in_progress/ready)
        // C) UNCLAIMED READY (15 orders: status ready, due_at 16-35 days ago)
        // D) FRESH READY FOR PICKUP (15 orders: status ready, due today/tomorrow)
        // E) IN PROGRESS (15 orders: status in_progress, due in 2-4 days)
        // F) COLLECTED (15 orders: 5 with released customer debt, 10 fully paid)
        // Total = 100 orders

        $orderConfigs = [];

        // A) OVERDUE (20 orders)
        for ($i = 0; $i < 20; $i++) {
            $daysAgo = rand(1, 7);
            $receivedDaysAgo = $daysAgo + rand(1, 4);
            $orderConfigs[] = [
                'group' => 'overdue',
                'status' => $i < 8 ? 'received' : 'in_progress',
                'received_at' => $now->copy()->subDays($receivedDaysAgo)->setTime(rand(9, 14), rand(10, 50)),
                'due_at' => $now->copy()->subDays($daysAgo)->setTime(rand(14, 18), 0),
                'has_balance' => ($i % 4 !== 0), // 75% have balance due
                'collected' => false,
            ];
        }

        // B) DUE TODAY (20 orders)
        for ($i = 0; $i < 20; $i++) {
            $receivedDaysAgo = rand(1, 3);
            $statusPool = ['received', 'in_progress', 'in_progress', 'ready'];
            $orderConfigs[] = [
                'group' => 'due_today',
                'status' => $statusPool[$i % count($statusPool)],
                'received_at' => $now->copy()->subDays($receivedDaysAgo)->setTime(rand(8, 12), rand(0, 59)),
                'due_at' => $now->copy()->setTime(rand(13, 19), rand(0, 3) * 15),
                'has_balance' => ($i % 3 !== 0), // 67% have balance due
                'collected' => false,
            ];
        }

        // C) UNCLAIMED READY (15 orders)
        for ($i = 0; $i < 15; $i++) {
            $unclaimedDays = rand(16, 40);
            $receivedDays = $unclaimedDays + rand(2, 4);
            $orderConfigs[] = [
                'group' => 'unclaimed',
                'status' => 'ready',
                'received_at' => $now->copy()->subDays($receivedDays)->setTime(rand(9, 16), 0),
                'due_at' => $now->copy()->subDays($unclaimedDays)->setTime(rand(14, 18), 0),
                'has_balance' => ($i % 3 !== 1), // 67% have balance due
                'collected' => false,
            ];
        }

        // D) FRESH READY FOR PICKUP (15 orders)
        for ($i = 0; $i < 15; $i++) {
            $receivedDays = rand(2, 4);
            $orderConfigs[] = [
                'group' => 'fresh_ready',
                'status' => 'ready',
                'received_at' => $now->copy()->subDays($receivedDays)->setTime(rand(9, 14), 0),
                'due_at' => $now->copy()->addDays(rand(0, 2))->setTime(rand(14, 18), 0),
                'has_balance' => ($i % 2 === 1), // 50% have balance
                'collected' => false,
            ];
        }

        // E) IN PROGRESS (15 orders)
        for ($i = 0; $i < 15; $i++) {
            $receivedHours = rand(4, 36);
            $orderConfigs[] = [
                'group' => 'fresh_in_progress',
                'status' => 'in_progress',
                'received_at' => $now->copy()->subHours($receivedHours),
                'due_at' => $now->copy()->addDays(rand(2, 5))->setTime(rand(14, 18), 0),
                'has_balance' => ($i % 3 !== 0), // 67% have balance
                'collected' => false,
            ];
        }

        // F) COLLECTED (15 orders)
        for ($i = 0; $i < 15; $i++) {
            $collectedDays = rand(1, 10);
            $receivedDays = $collectedDays + 3;
            // 5 collected orders were picked up with unpaid balance released to customer debt
            $hasBalance = ($i < 5);
            $orderConfigs[] = [
                'group' => 'collected',
                'status' => 'collected',
                'received_at' => $now->copy()->subDays($receivedDays)->setTime(rand(9, 12), 0),
                'due_at' => $now->copy()->subDays($collectedDays + 1)->setTime(16, 0),
                'has_balance' => $hasBalance,
                'collected' => true,
                'collected_at' => $now->copy()->subDays($collectedDays)->setTime(rand(14, 18), rand(10, 50)),
                'paid_split' => ($i >= 5 && $i % 2 === 1), // deposit + pickup payment history
            ];
        }

        // ── 4. Insert Orders, Line Items, Histories & Payments ───────────────────
        DB::beginTransaction();
        try {
            $nextNum = 101;

            foreach ($orderConfigs as $idx => $cfg) {
                $orderNumber = 'LDY-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
                $nextNum++;
                $customer = $customers[$idx % count($customers)];

                // Pick 1 to 4 service items
                $numItems = rand(1, 4);
                $selectedServices = $services->random(min($numItems, $services->count()));

                $subtotal = 0;
                $lineItems = [];

                foreach ($selectedServices as $srv) {
                    $qty = $srv->pricing_type === 'per_kg' ? (float) rand(2, 7) : (float) rand(1, 3);
                    $price = (float) $srv->price;
                    $lineTotal = round($qty * $price, 2);
                    $subtotal += $lineTotal;

                    $note = $sampleGarmentNotes[array_rand($sampleGarmentNotes)];

                    $lineItems[] = [
                        'service_id' => $srv->id,
                        'service_name' => $srv->name,
                        'pricing_type' => $srv->pricing_type,
                        'unit_price' => $price,
                        'quantity' => $qty,
                        'line_total' => $lineTotal,
                        'note' => $note ?: null,
                    ];
                }

                $taxAmount = round($subtotal * 0.075, 2); // 7.5% VAT
                $total = round($subtotal + $taxAmount, 2);

                // Deposit / Paid calculation
                $amountPaid = 0;
                $balance = $total;

                if (!$cfg['has_balance']) {
                    // Fully paid
                    $amountPaid = $total;
                    $balance = 0;
                } else {
                    // Partial payment or unpaid
                    $partialOption = rand(0, 3);
                    if ($partialOption === 0) {
                        $amountPaid = 0;
                        $balance = $total;
                    } elseif ($partialOption === 1) {
                        $amountPaid = round($total * 0.5, 2);
                        $balance = round($total - $amountPaid, 2);
                    } elseif ($partialOption === 2) {
                        $amountPaid = round($total * 0.3, 2);
                        $balance = round($total - $amountPaid, 2);
                    } else {
                        $amountPaid = round($total * 0.7, 2);
                        $balance = round($total - $amountPaid, 2);
                    }
                }

                // Create Order
                $order = LaundryOrder::create([
                    'branch_id' => $branch->id,
                    'order_number' => $orderNumber,
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                    'status' => $cfg['status'],
                    'received_at' => $cfg['received_at'],
                    'due_at' => $cfg['due_at'],
                    'collected_at' => $cfg['collected'] ? ($cfg['collected_at'] ?? $now) : null,
                    'collected_by_user_id' => $cfg['collected'] ? $user->id : null,
                    'collected_by_name' => $cfg['collected'] ? $user->name : null,
                    'created_by' => $user->id,
                    'subtotal' => $subtotal,
                    'discount_amount' => 0,
                    'discount_type' => 'fixed',
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                    'amount_paid' => $amountPaid,
                    'balance' => $balance,
                    'note' => rand(0, 3) === 1 ? 'Customer requested SMS notification when ready.' : null,
                ]);

                // If order was collected with an outstanding balance, record debit to customer's debt ledger
                if ($cfg['status'] === 'collected' && $balance > 0.01) {
                    $balanceBefore = (float) $customer->debt_bal;
                    $balanceAfter = round($balanceBefore + $balance, 2);

                    DebtPayment::create([
                        'branch_id' => $branch->id,
                        'customer_id' => $customer->id,
                        'user_id' => $user->id,
                        'laundry_order_id' => $order->id,
                        'source' => 'laundry',
                        'amount' => $balance,
                        'type' => 'debit',
                        'narration' => "Unpaid balance released on laundry order #{$order->order_number}",
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'created_at' => $cfg['collected_at'] ?? $now,
                    ]);

                    $customer->update(['debt_bal' => $balanceAfter]);
                }

                // Create Item Lines
                foreach ($lineItems as $item) {
                    $order->items()->create($item);
                }

                // Create Status History
                $order->statusHistory()->create([
                    'from_status' => null,
                    'to_status' => 'received',
                    'changed_by' => $user->id,
                    'changed_at' => $cfg['received_at'],
                    'note' => 'Order intake created',
                ]);

                if (in_array($cfg['status'], ['in_progress', 'ready', 'collected'])) {
                    $order->statusHistory()->create([
                        'from_status' => 'received',
                        'to_status' => 'in_progress',
                        'changed_by' => $user->id,
                        'changed_at' => $cfg['received_at']->copy()->addHours(rand(2, 6)),
                        'note' => 'Sorting, tagging and washing in progress',
                    ]);
                }

                if (in_array($cfg['status'], ['ready', 'collected'])) {
                    $readyAt = $cfg['status'] === 'ready' && $cfg['group'] === 'unclaimed'
                        ? $cfg['due_at']->copy()->subDays(1)
                        : $cfg['received_at']->copy()->addDays(rand(1, 2));

                    $order->statusHistory()->create([
                        'from_status' => 'in_progress',
                        'to_status' => 'ready',
                        'changed_by' => $user->id,
                        'changed_at' => $readyAt,
                        'note' => 'Garments pressed, bagged and moved to pickup rack',
                    ]);
                }

                if ($cfg['status'] === 'collected') {
                    $order->statusHistory()->create([
                        'from_status' => 'ready',
                        'to_status' => 'collected',
                        'changed_by' => $user->id,
                        'changed_at' => $cfg['collected_at'] ?? $now,
                        'note' => 'Customer inspected and picked up all garments in good condition',
                    ]);
                }

                // Create Payment records
                if (!empty($cfg['paid_split'])) {
                    // Split payment: deposit at intake + balance at pickup
                    $depositAmt = round($total * 0.4, 2);
                    $pickupAmt = round($total - $depositAmt, 2);

                    $method1 = $paymentMethods[array_rand($paymentMethods)];
                    $order->payments()->create([
                        'branch_id' => $branch->id,
                        'type' => 'payment',
                        'amount' => $depositAmt,
                        'method' => $method1,
                        'reference' => $method1 === 'Cash' ? null : 'TXN-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                        'recorded_by' => $user->id,
                        'paid_at' => $cfg['received_at'],
                        'note' => 'Initial 40% deposit received at intake',
                    ]);

                    $method2 = $paymentMethods[array_rand($paymentMethods)];
                    $order->payments()->create([
                        'branch_id' => $branch->id,
                        'type' => 'payment',
                        'amount' => $pickupAmt,
                        'method' => $method2,
                        'reference' => $method2 === 'Cash' ? null : 'TXN-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                        'recorded_by' => $user->id,
                        'paid_at' => $cfg['collected_at'] ?? $now,
                        'note' => 'Final balance payment completed upon pickup',
                    ]);
                } elseif ($amountPaid > 0) {
                    $method = $paymentMethods[array_rand($paymentMethods)];
                    $order->payments()->create([
                        'branch_id' => $branch->id,
                        'type' => 'payment',
                        'amount' => $amountPaid,
                        'method' => $method,
                        'reference' => $method === 'Cash' ? null : 'TXN-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                        'recorded_by' => $user->id,
                        'paid_at' => $cfg['received_at'],
                        'note' => $amountPaid == $total ? 'Full payment received at intake' : 'Initial deposit payment',
                    ]);
                }
            }

            DB::commit();
            $this->command?->info("Successfully created " . count($orderConfigs) . " diverse test laundry orders for '{$branch->name}'!");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command?->error("Failed to seed orders: " . $e->getMessage());
            throw $e;
        }
    }
}
