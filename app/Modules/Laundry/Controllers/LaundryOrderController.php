<?php

namespace App\Modules\Laundry\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PosCustomer;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Models\LaundryService;
use App\Modules\Laundry\Models\LaundrySetting;
use App\Modules\Laundry\Services\LaundryOrderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LaundryOrderController extends Controller
{
    public function __construct(
        protected LaundryOrderService $orderService
    ) {}

    /**
     * Orders board and list view.
     */
    public function index(Request $request)
    {
        $branch = current_branch();
        $search = $request->query('search', '');
        $status = $request->query('status', '');
        $filterOverdue = $request->boolean('overdue');
        $filterDueToday = $request->boolean('due_today');
        $filterUnclaimed = $request->boolean('unclaimed');
        $filterUnpaid = $request->boolean('unpaid');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $viewMode = $request->query('view', 'board'); // 'board' or 'list'

        $settings = LaundrySetting::forBranch($branch->id);

        $query = LaundryOrder::where('branch_id', $branch->id)
            ->with(['items', 'customer'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('order_number', 'like', "%{$search}%")
                       ->orWhere('customer_name', 'like', "%{$search}%")
                       ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            })
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($filterOverdue, function ($q) {
                $q->whereNotIn('status', ['collected', 'cancelled'])
                  ->where('due_at', '<', Carbon::now());
            })
            ->when($filterDueToday, function ($q) {
                $q->whereDate('due_at', Carbon::today())
                  ->whereNotIn('status', ['collected', 'cancelled']);
            })
            ->when($filterUnclaimed, function ($q) use ($settings) {
                $threshold = Carbon::now()->subDays($settings->unclaimed_after_days ?: 14);
                $q->where('status', 'ready')->where('due_at', '<=', $threshold);
            })
            ->when($filterUnpaid, fn($q) => $q->where('balance', '>', 0))
            ->when($startDate, fn($q) => $q->whereDate('received_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('received_at', '<=', $endDate));

        // Group counts for the Board headers
        $boardCounts = [
            'all'         => LaundryOrder::where('branch_id', $branch->id)->count(),
            'received'    => LaundryOrder::where('branch_id', $branch->id)->where('status', 'received')->count(),
            'in_progress' => LaundryOrder::where('branch_id', $branch->id)->where('status', 'in_progress')->count(),
            'ready'       => LaundryOrder::where('branch_id', $branch->id)->where('status', 'ready')->count(),
            'collected'   => LaundryOrder::where('branch_id', $branch->id)->where('status', 'collected')->count(),
            'cancelled'   => LaundryOrder::where('branch_id', $branch->id)->where('status', 'cancelled')->count(),
            'overdue'     => LaundryOrder::where('branch_id', $branch->id)->whereNotIn('status', ['collected', 'cancelled'])->where('due_at', '<', Carbon::now())->count(),
        ];

        $orders = (clone $query)
            ->latest('id')
            ->paginate(24)
            ->withQueryString()
            ->through(fn($o) => [
                'id'             => $o->id,
                'order_number'   => $o->order_number,
                'customer_id'    => $o->customer_id,
                'customer_name'  => $o->customer_name,
                'customer_phone' => $o->customer_phone,
                'status'         => $o->status,
                'received_at'    => $o->received_at?->format('d M Y, h:i A'),
                'due_at'         => $o->due_at?->format('d M Y, h:i A'),
                'collected_at'   => $o->collected_at?->format('d M Y, h:i A'),
                'subtotal'       => (float) $o->subtotal,
                'discount_amount'=> (float) $o->discount_amount,
                'tax_amount'     => (float) $o->tax_amount,
                'total'          => (float) $o->total,
                'amount_paid'    => (float) $o->amount_paid,
                'balance'        => (float) $o->balance,
                'items_count'    => $o->items->count(),
                'items_summary'  => $o->items->map(fn($i) => "{$i->quantity}x {$i->service_name}")->join(', '),
                'is_overdue'     => $o->isOverdue(),
                'is_unclaimed'   => $o->isUnclaimed($settings->unclaimed_after_days ?: 14),
                'note'           => $o->note,
            ]);

        return Inertia::render('Laundry/Orders/Index', [
            'orders'       => $orders,
            'board_counts' => $boardCounts,
            'filters'      => [
                'search'      => $search,
                'status'      => $status,
                'overdue'     => $filterOverdue,
                'due_today'   => $filterDueToday,
                'unclaimed'   => $filterUnclaimed,
                'unpaid'      => $filterUnpaid,
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'view'        => $viewMode,
            ],
            'settings'     => $settings,
        ]);
    }

    /**
     * Intake form screen.
     */
    public function create()
    {
        $branch = current_branch();
        $posSettings = $branch->getSettings();
        $laundrySettings = LaundrySetting::forBranch($branch->id);

        $services = LaundryService::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'pricing_type', 'price']);

        $categories = $services->pluck('category')->filter()->unique()->values();

        return Inertia::render('Laundry/Orders/Create', [
            'services'   => $services,
            'categories' => $categories,
            'settings'   => [
                'default_turnaround_days' => $laundrySettings->default_turnaround_days ?: 2,
                'order_prefix'            => $laundrySettings->order_prefix ?: 'LDY-',
                'is_tax_enabled'          => (bool) $posSettings->is_tax_enabled,
                'tax_percentage'          => (float) ($posSettings->tax_percentage ?? 0),
                'allow_price_override'    => (bool) $laundrySettings->allow_price_override,
            ],
        ]);
    }

    /**
     * Store new order in atomic transaction.
     */
    public function store(Request $request)
    {
        $branch = current_branch();

        $validated = $request->validate([
            'customer_id'       => 'nullable|integer',
            // Quick customer creation if customer_id not present
            'customer_name'     => 'required_without:customer_id|nullable|string|max:255',
            'customer_phone'    => 'required_without:customer_id|nullable|string|max:50',
            'customer_address'  => 'nullable|string|max:500',
            'customer_email'    => 'nullable|email|max:255',

            'items'             => 'required|array|min:1',
            'items.*.service_id'   => 'nullable|integer',
            'items.*.service_name' => 'required|string|max:255',
            'items.*.pricing_type' => 'required|string|in:per_piece,per_kg',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.quantity'     => 'required|numeric|min:0.01',
            'items.*.note'         => 'nullable|string|max:500',

            'due_at'            => 'nullable|date',
            'discount_amount'   => 'nullable|numeric|min:0',
            'discount_type'     => 'nullable|string|in:percentage,fixed',
            'deposit_amount'    => 'nullable|numeric|min:0',
            'payment_method'    => 'nullable|string|max:50',
            'payment_reference' => 'nullable|string|max:100',
            'note'              => 'nullable|string|max:1000',
        ]);

        // If creating customer inline
        if (empty($validated['customer_id'])) {
            $customer = PosCustomer::create([
                'branch_id' => $branch->id,
                'name'      => trim($validated['customer_name']),
                'phone'     => trim($validated['customer_phone']),
                'address'   => $validated['customer_address'] ?? null,
                'debt_bal'  => 0,
            ]);
            $validated['customer_id'] = $customer->id;
        }

        $order = $this->orderService->createOrder($branch, $request->user('web'), $validated);

        return redirect()->route('laundry.orders.show', ['branch' => $branch->slug, 'order' => $order->id])
            ->with('success', "Order #{$order->order_number} created successfully.");
    }

    /**
     * Show order details, payments, and workflow history.
     */
    public function show(Request $request, $branchParam, LaundryOrder $order)
    {
        $branch = current_branch();
        $settings = LaundrySetting::forBranch($branch->id);

        $order->load([
            'items.service',
            'payments.recorder',
            'customer',
            'creator',
            'collectedBy',
            'statusHistory.changer',
        ]);

        $whatsAppUrl = null;
        if ($order->customer_phone) {
            $normalizedPhone = LaundryOrderService::normalizeWhatsAppPhone($order->customer_phone, $settings->default_country_code ?: '+234');
            $msg = str_replace(
                ['{customer_name}', '{order_number}', '{branch_name}', '{balance}'],
                [$order->customer_name, $order->order_number, $branch->name, '₦' . number_format($order->balance, 2)],
                $settings->whatsapp_template ?: "Hello {customer_name}, your order #{order_number} is ready at {branch_name}!"
            );
            $whatsAppUrl = "https://wa.me/{$normalizedPhone}?text=" . urlencode($msg);
        }

        return Inertia::render('Laundry/Orders/Show', [
            'order'         => [
                'id'                   => $order->id,
                'order_number'         => $order->order_number,
                'status'               => $order->status,
                'customer_id'          => $order->customer_id,
                'customer_name'        => $order->customer_name,
                'customer_phone'       => $order->customer_phone,
                'customer_debt'        => (float) ($order->customer?->debt_bal ?? 0),
                'received_at'          => $order->received_at?->format('d M Y, h:i A'),
                'due_at'               => $order->due_at?->format('d M Y, h:i A'),
                'collected_at'         => $order->collected_at?->format('d M Y, h:i A'),
                'collected_by_name'    => $order->collected_by_name,
                'created_by_name'      => $order->creator?->name ?? 'Staff',
                'subtotal'             => (float) $order->subtotal,
                'discount_amount'      => (float) $order->discount_amount,
                'discount_type'        => $order->discount_type,
                'tax_amount'           => (float) $order->tax_amount,
                'total'                => (float) $order->total,
                'amount_paid'          => (float) $order->amount_paid,
                'balance'              => (float) $order->balance,
                'note'                 => $order->note,
                'is_overdue'           => $order->isOverdue(),
                'is_unclaimed'         => $order->isUnclaimed($settings->unclaimed_after_days ?: 14),
                'items'                => $order->items->map(fn($i) => [
                    'id'           => $i->id,
                    'service_name' => $i->service_name,
                    'pricing_type' => $i->pricing_type,
                    'unit_price'   => (float) $i->unit_price,
                    'quantity'     => (float) $i->quantity,
                    'line_total'   => (float) $i->line_total,
                    'note'         => $i->note,
                ]),
                'payments'             => $order->payments->map(fn($p) => [
                    'id'              => $p->id,
                    'type'            => $p->type,
                    'amount'          => (float) $p->amount,
                    'method'          => $p->method,
                    'reference'       => $p->reference,
                    'recorded_by_name'=> $p->recorder?->name ?? 'Staff',
                    'paid_at'         => $p->paid_at?->format('d M Y, h:i A'),
                    'note'            => $p->note,
                ]),
                'history'              => $order->statusHistory->map(fn($h) => [
                    'id'              => $h->id,
                    'from_status'     => $h->from_status,
                    'to_status'       => $h->to_status,
                    'changed_by_name' => $h->changer?->name ?? 'Staff',
                    'changed_at'      => $h->changed_at?->format('d M Y, h:i A'),
                    'note'            => $h->note,
                ]),
            ],
            'whatsapp_url'  => $whatsAppUrl,
            'branch'        => [
                'name'    => $branch->name,
                'address' => $branch->address,
                'phone'   => $branch->phone,
            ],
            'settings'      => $settings,
        ]);
    }

    /**
     * Update order workflow status.
     */
    public function updateStatus(Request $request, $branchParam, LaundryOrder $order)
    {
        $validated = $request->validate([
            'status'            => 'required|string|in:received,in_progress,ready,collected,cancelled',
            'note'              => 'nullable|string|max:500',
            'collected_by_name' => 'nullable|string|max:255',
        ]);

        $this->orderService->updateStatus(
            $order,
            $validated['status'],
            $request->user('web'),
            $validated['note'] ?? null,
            $validated['collected_by_name'] ?? null
        );

        return back()->with('success', "Order status updated to '" . ucfirst(str_replace('_', ' ', $validated['status'])) . "'.");
    }

    /**
     * Add payment or balance settlement.
     */
    public function recordPayment(Request $request, $branchParam, LaundryOrder $order)
    {
        $validated = $request->validate([
            'amount'    => 'required|numeric|min:0.01',
            'method'    => 'required|string|max:50',
            'reference' => 'nullable|string|max:100',
            'note'      => 'nullable|string|max:500',
        ]);

        $this->orderService->recordPayment($order, $request->user('web'), $validated);

        return back()->with('success', 'Payment recorded successfully.');
    }

    public function storePayment(Request $request, $branchParam, LaundryOrder $order)
    {
        return $this->recordPayment($request, $branchParam, $order);
    }

    /**
     * Mark order as collected / picked up.
     */
    public function markCollected(Request $request, $branchParam, LaundryOrder $order)
    {
        $validated = $request->validate([
            'collected_by_name' => 'nullable|string|max:255',
            'note'              => 'nullable|string|max:500',
        ]);

        $this->orderService->updateStatus(
            $order,
            'collected',
            $request->user('web'),
            $validated['note'] ?? 'Order picked up by customer',
            $validated['collected_by_name'] ?? null
        );

        return back()->with('success', "Order #{$order->order_number} marked as collected.");
    }

    /**
     * Cancel an order.
     */
    public function cancel(Request $request, $branchParam, LaundryOrder $order)
    {
        $validated = $request->validate([
            'reason'        => 'required|string|max:500',
            'refund_method' => 'nullable|string|max:50',
            'refund_amount' => 'nullable|numeric|min:0',
        ]);

        $this->orderService->updateStatus(
            $order,
            'cancelled',
            $request->user('web'),
            $validated['reason']
        );

        // If refund details are provided and amount > 0
        if (!empty($validated['refund_amount']) && (float)$validated['refund_amount'] > 0) {
            $this->orderService->recordRefund(
                $order,
                $request->user('web'),
                [
                    'amount' => (float)$validated['refund_amount'],
                    'method' => $validated['refund_method'] ?? 'Cash',
                    'note'   => 'Cancellation refund: ' . $validated['reason'],
                ]
            );
        }

        return back()->with('success', "Order #{$order->order_number} has been cancelled.");
    }

    /**
     * Generate or redirect to ticket direct printing data.
     */
    public function printTicket(Request $request, $branchParam, LaundryOrder $order)
    {
        $branch = current_branch();
        $posSettings = $branch->getSettings();
        $laundrySettings = LaundrySetting::forBranch($branch->id);

        $order->load(['items', 'payments', 'customer']);

        return response()->json([
            'branch' => [
                'name'    => $branch->name,
                'address' => $branch->address,
                'phone'   => $branch->phone,
                'email'   => $branch->email,
            ],
            'order' => [
                'id'              => $order->id,
                'order_number'    => $order->order_number,
                'status'          => $order->status,
                'customer_name'   => $order->customer_name,
                'customer_phone'  => $order->customer_phone,
                'received_at'     => $order->received_at?->format('d/m/Y h:i A'),
                'due_at'          => $order->due_at?->format('d/m/Y h:i A'),
                'subtotal'        => (float) $order->subtotal,
                'discount_amount' => (float) $order->discount_amount,
                'tax_amount'      => (float) $order->tax_amount,
                'total'           => (float) $order->total,
                'amount_paid'     => (float) $order->amount_paid,
                'balance'         => (float) $order->balance,
                'items'           => $order->items->map(fn($i) => [
                    'name'        => $i->service_name,
                    'qty'         => (float) $i->quantity,
                    'pricing_type'=> $i->pricing_type,
                    'unit_price'  => (float) $i->unit_price,
                    'line_total'  => (float) $i->line_total,
                    'note'        => $i->note,
                ]),
            ],
            'settings' => [
                'receipt_footer' => $laundrySettings->receipt_footer ?: 'Thank you for choosing us! Please present this ticket at pickup.',
                'paper_size'     => $posSettings->receipt_paper_size ?: '80mm',
            ],
        ]);
    }

    /**
     * Get pre-filled WhatsApp click-to-chat URL.
     */
    public function whatsappLink(Request $request, $branchParam, LaundryOrder $order)
    {
        $branch = current_branch();
        $settings = LaundrySetting::forBranch($branch->id);

        $link = LaundryOrderService::generateWhatsAppLink($order, $settings);

        return response()->json([
            'url'   => $link,
            'phone' => $order->customer_phone,
        ]);
    }

    /**
     * Search existing customers by query (AJAX).
     */
    public function searchCustomers(Request $request)
    {
        $branch = current_branch();
        $query = trim($request->input('query', ''));

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $customers = PosCustomer::where('branch_id', $branch->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'phone', 'address', 'debt_bal']);

        return response()->json($customers);
    }

    /**
     * Quick-create a customer from laundry intake form.
     */
    public function quickCreateCustomer(Request $request)
    {
        $branch = current_branch();

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'required|string|max:50',
            'address' => 'nullable|string|max:500',
            'email'   => 'nullable|email|max:255',
        ]);

        // Warn if phone already exists in this branch
        $existing = PosCustomer::where('branch_id', $branch->id)
            ->where('phone', trim($validated['phone']))
            ->first();

        if ($existing) {
            return response()->json([
                'duplicate' => true,
                'message'   => "A customer with phone {$validated['phone']} already exists ({$existing->name}).",
                'customer'  => $existing,
            ], 200);
        }

        $customer = PosCustomer::create([
            'branch_id' => $branch->id,
            'name'      => trim($validated['name']),
            'phone'     => trim($validated['phone']),
            'address'   => $validated['address'] ?? null,
            'email'     => $validated['email'] ?? null,
            'debt_bal'  => 0,
        ]);

        return response()->json([
            'duplicate' => false,
            'customer'  => $customer,
        ], 201);
    }
}
