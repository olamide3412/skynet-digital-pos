<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StoreCustomerRequest;
use App\Models\DebtPayment;
use App\Models\PosCustomer;
use App\Models\Sale;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Services\LaundryOrderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'all');
        $branch = current_branch();

        $baseQuery = PosCustomer::query()
            ->when($branch, fn($q) => $q->where('branch_id', $branch->id));

        // Calculate counts for quick tab navigation
        $allCount       = (clone $baseQuery)->count();
        $watchlistCount = (clone $baseQuery)->where('watch_list', true)->count();
        $debtCount      = (clone $baseQuery)->where('debt_bal', '>', 0)->count();

        $customers = (clone $baseQuery)
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->when($tab === 'watchlist', fn ($q) => $q->where('watch_list', true))
            ->when($tab === 'debt', fn ($q) => $q->where('debt_bal', '>', 0))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters'   => [
                'search' => $request->search ?? '',
                'tab'    => $tab,
            ],
            'counts'    => [
                'all'       => $allCount,
                'watchlist' => $watchlistCount,
                'debt'      => $debtCount,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Customers/Create');
    }

    public function store(StoreCustomerRequest $request)
    {
        $branch = current_branch();
        $data = $request->validated();
        $data['branch_id']       = $branch?->id;
        $data['email']           = !empty($data['email']) ? trim($data['email']) : null;
        
        $sameAsPhone = filter_var($request->input('same_as_phone', false), FILTER_VALIDATE_BOOLEAN);
        if ($sameAsPhone || empty($data['whatsapp_number'])) {
            $data['whatsapp_number'] = $data['phone'];
        } else {
            $data['whatsapp_number'] = trim($data['whatsapp_number']);
        }

        $data['address']         = $data['address'] ?? 'NA';
        $data['note']            = $data['note'] ?? 'No Note';
        $data['contact_name']    = $data['contact_name'] ?? 'NA';
        $data['contact_phone']   = $data['contact_phone'] ?? 'NA';
        $data['contact_address'] = $data['contact_address'] ?? 'NA';

        unset($data['same_as_phone']);

        PosCustomer::create($data);
        return redirect()->route('pos.customers.index', ['branch' => $branch?->slug])->with('success', 'Customer created.');
    }

    public function show($branchParam, PosCustomer $customer)
    {
        $branch = current_branch();
        if ($branch && $customer->branch_id && $customer->branch_id !== $branch->id) {
            abort(404);
        }

        $customer->load(['sales.saleOrders', 'debtPayments.user']);
        return Inertia::render('Customers/Show', ['customer' => $customer]);
    }

    public function edit($branchParam, PosCustomer $customer)
    {
        $branch = current_branch();
        if ($branch && $customer->branch_id && $customer->branch_id !== $branch->id) {
            abort(404);
        }

        return Inertia::render('Customers/Edit', ['customer' => $customer]);
    }

    public function update(StoreCustomerRequest $request, $branchParam, PosCustomer $customer)
    {
        $branch = current_branch();
        if ($branch && $customer->branch_id && $customer->branch_id !== $branch->id) {
            abort(404);
        }

        $data = $request->validated();
        $data['email']           = !empty($data['email']) ? trim($data['email']) : null;

        $sameAsPhone = filter_var($request->input('same_as_phone', false), FILTER_VALIDATE_BOOLEAN);
        if ($sameAsPhone) {
            $data['whatsapp_number'] = $data['phone'];
        } elseif (!empty($data['whatsapp_number'])) {
            $data['whatsapp_number'] = trim($data['whatsapp_number']);
        } else {
            $data['whatsapp_number'] = $data['phone'];
        }

        $data['address']         = $data['address'] ?? 'NA';
        $data['note']            = $data['note'] ?? 'No Note';
        $data['contact_name']    = $data['contact_name'] ?? 'NA';
        $data['contact_phone']   = $data['contact_phone'] ?? 'NA';
        $data['contact_address'] = $data['contact_address'] ?? 'NA';

        unset($data['same_as_phone']);

        $customer->update($data);
        return redirect()->route('pos.customers.index', ['branch' => $branch?->slug])->with('success', 'Customer updated.');
    }

    public function destroy($branchParam, PosCustomer $customer)
    {
        $branch = current_branch();
        if ($branch && $customer->branch_id && $customer->branch_id !== $branch->id) {
            abort(404);
        }

        // 1. Strictly block deleting customers who owe money
        if ((float) $customer->debt_bal > 0) {
            return back()->withErrors([
                'customer' => "Cannot delete customer \"{$customer->name}\" because they have an outstanding debt balance of ₦" . number_format($customer->debt_bal, 2) . ". All debts must be fully settled before deleting."
            ]);
        }

        // 2. Protect transaction audit trail if sales or debt logs exist
        if ($customer->sales()->exists() || $customer->debtPayments()->exists()) {
            return back()->withErrors([
                'customer' => "Cannot delete customer \"{$customer->name}\" because they have sales transaction history on record. To preserve accounting integrity, existing customer accounts with sales history cannot be removed."
            ]);
        }

        $customerName = $customer->name;
        $customer->delete();
        return redirect()->route('pos.customers.index')->with('success', "Customer \"{$customerName}\" deleted successfully.");
    }

    /** Toggle customer watchlist flag */
    public function toggleWatchlist($branchParam, PosCustomer $customer)
    {
        $branch = current_branch();
        if ($branch && $customer->branch_id && $customer->branch_id !== $branch->id) {
            abort(404);
        }

        $customer->update(['watch_list' => !$customer->watch_list]);
        $status = $customer->watch_list ? 'added to' : 'removed from';
        return back()->with('success', "Customer \"{$customer->name}\" {$status} watch list.");
    }

    /** Update note only without full profile editing */
    public function updateNote(Request $request, $branchParam, PosCustomer $customer)
    {
        $data = $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $customer->update([
            'note' => empty(trim($data['note'] ?? '')) ? 'No Note' : trim($data['note']),
        ]);

        return back()->with('success', "Note for customer \"{$customer->name}\" updated.");
    }

    /** API: search for POS customer selector */
    public function search(Request $request)
    {
        $branch = current_branch();
        $q = trim($request->input('q', $request->input('query', $request->input('search', ''))));

        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $query = PosCustomer::query()->search($q);

        $branchId = $branch ? $branch->id : 0;

        $results = $query
            ->orderByRaw("CASE WHEN branch_id = ? THEN 0 ELSE 1 END", [$branchId])
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'phone', 'whatsapp_number', 'email', 'address', 'debt_bal', 'watch_list', 'branch_id']);

        return response()->json($results);
    }

    /**
     * Full debt ledger page for a customer — shows all transactions + running balance.
     */
    public function debtLedger($branchParam, PosCustomer $customer)
    {
        $transactions = DebtPayment::with(['user', 'sale', 'laundryOrder'])
            ->where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Summary stats
        $totalDebited  = $transactions->where('type', 'debit')->sum('amount');
        $totalCredited = $transactions->where('type', 'credit')->sum('amount');

        return Inertia::render('Customers/DebtLedger', [
            'customer'      => $customer,
            'transactions'  => $transactions,
            'summary' => [
                'current_balance' => $customer->debt_bal,
                'total_debited'   => $totalDebited,
                'total_credited'  => $totalCredited,
                'total_entries'   => $transactions->count(),
            ],
        ]);
    }

    /**
     * Record a debt payment (credit) or charge (debit) — full audit trail with FIFO waterfall.
     */
    public function recordDebt(Request $request, $branchParam, PosCustomer $customer)
    {
        $data = $request->validate([
            'amount'    => 'required|numeric|min:0.01',
            'type'      => 'required|in:debit,credit',
            'method'    => 'nullable|string|max:50',
            'narration' => 'nullable|string|max:255',
        ]);

        // For credits — reject if amount exceeds outstanding balance
        if ($data['type'] === 'credit' && $data['amount'] > $customer->debt_bal) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => 'Payment of ₦' . number_format($data['amount'], 2) .
                            ' exceeds the outstanding balance of ₦' . number_format($customer->debt_bal, 2) . '.',
            ]);
        }

        DB::transaction(function () use ($data, $customer) {
            $branch = current_branch();
            $user   = Auth::user();

            if ($data['type'] === 'credit') {
                // ── Approach 1: Source-Driven Ledger Debit Matching (FIFO) ────
                $paymentAmount      = (float) $data['amount'];
                $runningCustomerBal = (float) $customer->debt_bal;
                $paymentMethod      = !empty($data['method']) ? $data['method'] : 'Cash';
                $laundryService     = app(LaundryOrderService::class);

                // Fetch customer's debit entries in reverse chronological order to identify open debts
                $recentDebits = DebtPayment::where('customer_id', $customer->id)
                    ->where('type', 'debit')
                    ->orderBy('created_at', 'desc')
                    ->orderBy('id', 'desc')
                    ->get();

                $debts = [];
                $unexplainedBalance = $runningCustomerBal;
                $linkedOrderIds = [];

                foreach ($recentDebits as $debit) {
                    if ($unexplainedBalance <= 0.001) {
                        break;
                    }

                    $src = $debit->source ?: 'pos';
                    $debitAmount = (float) $debit->amount;

                    if ($src === 'laundry' && $debit->laundry_order_id) {
                        $order = LaundryOrder::with('branch')->find($debit->laundry_order_id);
                        if (!$order || (float) $order->balance <= 0.001) {
                            continue;
                        }

                        $openAmount = min((float) $order->balance, $unexplainedBalance);
                        $debts[] = [
                            'source'           => 'laundry',
                            'date'             => $debit->created_at,
                            'unpaid'           => $openAmount,
                            'laundry_order_id' => $order->id,
                            'model'            => $order,
                            'ref'              => $order->order_number,
                        ];
                        $unexplainedBalance = round(max(0, $unexplainedBalance - $openAmount), 2);

                    } elseif ($src === 'pos') {
                        $openAmount = min($debitAmount, $unexplainedBalance);
                        $debts[] = [
                            'source'           => 'pos',
                            'date'             => $debit->created_at,
                            'unpaid'           => $openAmount,
                            'sale_id'          => $debit->sale_id,
                            'model'            => null,
                            'ref'              => $debit->narration ?: 'POS Retail Debt',
                        ];
                        $unexplainedBalance = round(max(0, $unexplainedBalance - $openAmount), 2);

                    } else {
                        // 'manual' or any future module (e.g., 'car_service')
                        $openAmount = min($debitAmount, $unexplainedBalance);
                        $debts[] = [
                            'source'           => $src,
                            'date'             => $debit->created_at,
                            'unpaid'           => $openAmount,
                            'model'            => null,
                            'ref'              => $debit->narration ?: 'Manual Charge',
                        ];
                        $unexplainedBalance = round(max(0, $unexplainedBalance - $openAmount), 2);
                    }
                }

                // If customer has an older unallocated balance (e.g., initial migration debt)
                if ($unexplainedBalance > 0.001) {
                    $debts[] = [
                        'source'           => 'manual',
                        'date'             => $customer->created_at,
                        'unpaid'           => $unexplainedBalance,
                        'model'            => null,
                        'ref'              => 'Opening Debt Balance',
                    ];
                }

                // Reverse array so we process FIFO (Oldest debt first)
                $debts = array_reverse($debts);

                $remainingPayment = $paymentAmount;

                foreach ($debts as $debtItem) {
                    if ($remainingPayment <= 0.001) {
                        break;
                    }

                    $allocAmount = min($remainingPayment, $debtItem['unpaid']);
                    if ($allocAmount <= 0) {
                        continue;
                    }

                    $remainingPayment   = round($remainingPayment - $allocAmount, 2);
                    $balBefore          = $runningCustomerBal;
                    $runningCustomerBal = round(max(0, $runningCustomerBal - $allocAmount), 2);

                    if ($debtItem['source'] === 'laundry' && !empty($debtItem['model'])) {
                        /** @var LaundryOrder $order */
                        $order = $debtItem['model'];

                        // Record payment on laundry order (syncDebtLedger = false to avoid duplicate debiting)
                        $laundryService->recordPayment(
                            $order,
                            $user,
                            [
                                'amount'    => $allocAmount,
                                'method'    => $paymentMethod,
                                'reference' => 'LPAY-DEBT-' . strtoupper(substr(uniqid(), -6)),
                                'note'      => "Debt ledger settlement for order #{$order->order_number}",
                            ],
                            false
                        );

                        $debtPayment = DebtPayment::create([
                            'branch_id'        => $branch?->id,
                            'customer_id'      => $customer->id,
                            'user_id'          => $user->id,
                            'amount'           => $allocAmount,
                            'type'             => 'credit',
                            'source'           => 'laundry',
                            'laundry_order_id' => $order->id,
                            'narration'        => "Debt payment applied to laundry order #{$order->order_number}" . (!empty($data['narration']) ? " ({$data['narration']})" : ""),
                            'balance_before'   => $balBefore,
                            'balance_after'    => $runningCustomerBal,
                        ]);

                        \App\Modules\Accounting\Services\SalePostingService::postDebtPayment($debtPayment);

                    } elseif ($debtItem['source'] === 'pos') {
                        // POS retail debt settlement: never alters historical Sales table
                        $debtPayment = DebtPayment::create([
                            'branch_id'      => $branch?->id,
                            'customer_id'    => $customer->id,
                            'user_id'        => $user->id,
                            'amount'         => $allocAmount,
                            'type'           => 'credit',
                            'source'         => 'pos',
                            'sale_id'        => $debtItem['sale_id'] ?? null,
                            'narration'      => !empty($data['narration'])
                                ? $data['narration']
                                : "Debt payment applied to POS retail balance",
                            'balance_before' => $balBefore,
                            'balance_after'  => $runningCustomerBal,
                        ]);

                        \App\Modules\Accounting\Services\SalePostingService::postDebtPayment($debtPayment);

                    } else {
                        // Manual charge or other module settlement
                        $debtPayment = DebtPayment::create([
                            'branch_id'      => $branch?->id,
                            'customer_id'    => $customer->id,
                            'user_id'        => $user->id,
                            'amount'         => $allocAmount,
                            'type'           => 'credit',
                            'source'         => $debtItem['source'],
                            'narration'      => !empty($data['narration'])
                                ? $data['narration']
                                : "Payment applied to {$debtItem['ref']}",
                            'balance_before' => $balBefore,
                            'balance_after'  => $runningCustomerBal,
                        ]);

                        \App\Modules\Accounting\Services\SalePostingService::postDebtPayment($debtPayment);
                    }
                }

                // If any residual payment remains
                if ($remainingPayment > 0.001) {
                    $balBefore          = $runningCustomerBal;
                    $runningCustomerBal = round(max(0, $runningCustomerBal - $remainingPayment), 2);

                    $debtPayment = DebtPayment::create([
                        'branch_id'      => $branch?->id,
                        'customer_id'    => $customer->id,
                        'user_id'        => $user->id,
                        'amount'         => $remainingPayment,
                        'type'           => 'credit',
                        'source'         => 'manual',
                        'narration'      => $data['narration'] ?: 'Customer debt payment',
                        'balance_before' => $balBefore,
                        'balance_after'  => $runningCustomerBal,
                    ]);

                    \App\Modules\Accounting\Services\SalePostingService::postDebtPayment($debtPayment);
                }

                $customer->update(['debt_bal' => $runningCustomerBal]);

            } else {
                // ── Manual Charge (Debit) ──────────────────────────────────────
                $balanceBefore = (float) $customer->debt_bal;
                $balanceAfter  = round($balanceBefore + $data['amount'], 2);

                DebtPayment::create([
                    'branch_id'      => $branch?->id,
                    'customer_id'    => $customer->id,
                    'user_id'        => $user->id,
                    'amount'         => $data['amount'],
                    'type'           => 'debit',
                    'source'         => 'manual',
                    'narration'      => $data['narration'] ?: 'Manual charge',
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $balanceAfter,
                ]);

                $customer->update(['debt_bal' => $balanceAfter]);
            }
        });

        return back()->with('success', $data['type'] === 'credit'
            ? 'Payment of ₦' . number_format($data['amount'], 2) . ' recorded successfully.'
            : 'Charge of ₦' . number_format($data['amount'], 2) . ' added successfully.'
        );
    }
}
