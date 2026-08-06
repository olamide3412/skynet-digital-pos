<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StoreCustomerRequest;
use App\Models\DebtPayment;
use App\Models\PosCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = PosCustomer::when($request->search, fn ($q) =>
                $q->where('name', 'like', '%'.$request->search.'%')
                  ->orWhere('phone', 'like', '%'.$request->search.'%'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters'   => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Customers/Create');
    }

    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();
        $data['branch_id']       = current_branch()?->id;
        $data['address']         = $data['address'] ?? 'NA';
        $data['note']            = $data['note'] ?? 'No Note';
        $data['contact_name']    = $data['contact_name'] ?? 'NA';
        $data['contact_phone']   = $data['contact_phone'] ?? 'NA';
        $data['contact_address'] = $data['contact_address'] ?? 'NA';

        PosCustomer::create($data);
        return redirect()->route('pos.customers.index')->with('success', 'Customer created.');
    }

    public function show($branchParam, PosCustomer $customer)
    {
        $customer->load(['sales.saleOrders', 'debtPayments.user']);
        return Inertia::render('Customers/Show', ['customer' => $customer]);
    }

    public function edit($branchParam, PosCustomer $customer)
    {
        return Inertia::render('Customers/Edit', ['customer' => $customer]);
    }

    public function update(StoreCustomerRequest $request, $branchParam, PosCustomer $customer)
    {
        $data = $request->validated();
        $data['address']         = $data['address'] ?? 'NA';
        $data['note']            = $data['note'] ?? 'No Note';
        $data['contact_name']    = $data['contact_name'] ?? 'NA';
        $data['contact_phone']   = $data['contact_phone'] ?? 'NA';
        $data['contact_address'] = $data['contact_address'] ?? 'NA';

        $customer->update($data);
        return redirect()->route('pos.customers.index')->with('success', 'Customer updated.');
    }

    public function destroy($branchParam, PosCustomer $customer)
    {
        $customer->delete();
        return redirect()->route('pos.customers.index')->with('success', 'Customer deleted.');
    }

    /** API: search for POS customer selector */
    public function search(Request $request)
    {
        return response()->json(
            PosCustomer::where('name', 'like', '%'.$request->q.'%')
                ->orWhere('phone', 'like', '%'.$request->q.'%')
                ->limit(10)->get(['id', 'name', 'phone', 'debt_bal'])
        );
    }

    /**
     * Full debt ledger page for a customer — shows all transactions + running balance.
     */
    public function debtLedger($branchParam, PosCustomer $customer)
    {
        $transactions = DebtPayment::with('user')
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
     * Record a debt payment (credit) or charge (debit) — full audit trail.
     */
    public function recordDebt(Request $request, $branchParam, PosCustomer $customer)
    {
        $data = $request->validate([
            'amount'    => 'required|numeric|min:0.01',
            'type'      => 'required|in:debit,credit',
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
            $branch        = current_branch();
            $balanceBefore = (float) $customer->debt_bal;

            $balanceAfter = $data['type'] === 'credit'
                ? $balanceBefore - $data['amount']
                : $balanceBefore + $data['amount'];

            DebtPayment::create([
                'branch_id'      => $branch?->id,
                'customer_id'    => $customer->id,
                'user_id'        => Auth::id(),
                'amount'         => $data['amount'],
                'type'           => $data['type'],
                'narration'      => $data['narration'] ?? ($data['type'] === 'credit' ? 'Payment received' : 'Manual charge'),
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
            ]);

            $customer->update(['debt_bal' => $balanceAfter]);
        });

        return redirect()
            ->route('pos.customers.debt-ledger', $customer)
            ->with('success', $data['type'] === 'credit'
                ? 'Payment of ₦' . number_format($data['amount'], 2) . ' recorded successfully.'
                : 'Charge of ₦' . number_format($data['amount'], 2) . ' added successfully.'
            );
    }
}
