<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\PosSettings;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Modules\Accounting\Services\InventoryPostingService;
use App\Modules\Accounting\Services\SalePostingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnpostedTransactionsController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();
        $settings = PosSettings::forBranch($branch->id);

        if (!$settings->accounting_started_at) {
            return Inertia::render('Accounting/Unposted/Index', [
                'unpostedSales'     => [],
                'unpostedExpenses'  => [],
                'unpostedPurchases' => [],
                'accountingActive'  => false,
            ]);
        }

        $startDate = $settings->accounting_started_at->startOfDay();

        // 1. Unposted Sales
        $unpostedSales = Sale::where('branch_id', $branch->id)
            ->where('created_at', '>=', $startDate)
            ->whereNotExists(function ($q) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))
                  ->from('journal_entries')
                  ->whereColumn('journal_entries.source_id', 'sales.id')
                  ->where('journal_entries.source_type', Sale::class)
                  ->where('journal_entries.event', 'sale_completed')
                  ->whereNull('journal_entries.reversal_of_id');
            })
            ->latest()
            ->take(50)
            ->get(['id', 'receipt_id', 'final_total', 'payment_method', 'created_at']);

        // 2. Unposted Expenses
        $unpostedExpenses = Expense::with('category')
            ->where('branch_id', $branch->id)
            ->where('expense_date', '>=', $startDate->toDateString())
            ->whereNotExists(function ($q) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))
                  ->from('journal_entries')
                  ->whereColumn('journal_entries.source_id', 'expenses.id')
                  ->where('journal_entries.source_type', Expense::class)
                  ->where('journal_entries.event', 'expense_recorded')
                  ->whereNull('journal_entries.reversal_of_id');
            })
            ->latest('expense_date')
            ->take(50)
            ->get();

        // 3. Unposted Received POs
        $unpostedPurchases = PurchaseOrder::with('vendor')
            ->where('branch_id', $branch->id)
            ->whereIn('status', ['Received', 'Partial'])
            ->where('created_at', '>=', $startDate)
            ->whereNotExists(function ($q) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))
                  ->from('journal_entries')
                  ->whereColumn('journal_entries.source_id', 'purchase_orders.id')
                  ->where('journal_entries.source_type', PurchaseOrder::class)
                  ->where('journal_entries.event', 'like', 'purchase_receipt%')
                  ->whereNull('journal_entries.reversal_of_id');
            })
            ->latest()
            ->take(50)
            ->get();

        return Inertia::render('Accounting/Unposted/Index', [
            'unpostedSales'     => $unpostedSales,
            'unpostedExpenses'  => $unpostedExpenses,
            'unpostedPurchases' => $unpostedPurchases,
            'accountingActive'  => true,
            'startDate'         => $startDate->toDateString(),
        ]);
    }

    public function retry(Request $request)
    {
        $branch = current_branch();
        $validated = $request->validate([
            'type' => 'required|in:sale,expense,purchase',
            'id'   => 'required|integer',
        ]);

        $type = $validated['type'];
        $id = $validated['id'];

        $entry = null;

        if ($type === 'sale') {
            $sale = Sale::where('branch_id', $branch->id)->findOrFail($id);
            $entry = SalePostingService::postSale($sale);
        } elseif ($type === 'expense') {
            $expense = Expense::where('branch_id', $branch->id)->findOrFail($id);
            $entry = InventoryPostingService::postExpense($expense);
        } elseif ($type === 'purchase') {
            $po = PurchaseOrder::where('branch_id', $branch->id)->findOrFail($id);
            $receivedTotal = (float) $po->receivedItems()->sum(\Illuminate\Support\Facades\DB::raw('received_qty * cost'));
            if ($receivedTotal <= 0) {
                $receivedTotal = (float) $po->total_amount;
            }
            $entry = InventoryPostingService::postPurchaseOrderReceipt($po, $receivedTotal, auth()->id(), (string) time());
        }

        if ($entry) {
            return back()->with('success', "Transaction successfully posted to double-entry ledger (Entry #{$entry->entry_number}).");
        }

        return back()->with('error', 'Unable to post transaction. Please verify chart of accounts and balance constraints.');
    }
}
