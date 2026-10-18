<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InterBranchTransfer;
use App\Models\Item;
use App\Services\InterBranchTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InterBranchTransferController extends Controller
{
    protected function getBranch(Request $request, ?Branch $branch = null): Branch
    {
        return current_branch()
            ?? ($branch instanceof Branch ? $branch : null)
            ?? Branch::where('slug', $request->route('branch'))->firstOrFail();
    }

    /**
     * Display the Receiving Queue (incoming) and Sent Transfers (outgoing).
     */
    public function index(Request $request, ?Branch $branch = null): Response
    {
        $branch = $this->getBranch($request, $branch);
        $organization = $branch->organization;

        // Incoming Transfers (Receiving Queue)
        $incoming = InterBranchTransfer::where('destination_branch_id', $branch->id)
            ->with(['sourceBranch:id,name,slug', 'items.sourceItem', 'items.destinationItem', 'items.productGroup'])
            ->latest('id')
            ->paginate(15, ['*'], 'incoming_page');

        // Outgoing Transfers (Sent)
        $outgoing = InterBranchTransfer::where('source_branch_id', $branch->id)
            ->with(['destinationBranch:id,name,slug', 'items.sourceItem', 'items.destinationItem'])
            ->latest('id')
            ->paginate(15, ['*'], 'outgoing_page');

        // Other branches in same organization
        $siblingBranches = $organization
            ? $organization->branches()->where('id', '!=', $branch->id)->orderBy('name')->get(['id', 'name', 'slug'])
            : collect();

        $activeTab = $request->query('tab', 'incoming');

        return Inertia::render('Inventory/InterBranchTransfers/Index', [
            'incomingTransfers' => $incoming,
            'outgoingTransfers' => $outgoing,
            'siblingBranches'   => $siblingBranches,
            'activeTab'         => $activeTab,
            'hasCapability'     => $organization ? $organization->hasFeature('inter_branch_transfers') : true,
        ]);
    }

    /**
     * Show form to create a new inter-branch transfer.
     */
    public function create(Request $request, ?Branch $branch = null): Response
    {
        $branch = $this->getBranch($request, $branch);
        $organization = $branch->organization;
        $siblingBranches = $organization
            ? $organization->branches()->where('id', '!=', $branch->id)->orderBy('name')->get(['id', 'name', 'slug'])
            : collect();

        $items = Item::where('branch_id', $branch->id)
            ->with(['productGroupItem.productGroup'])
            ->select('id', 'branch_id', 'item_name', 'barcode_number', 'unit_label', 'buy_price', 'price', 'front_store_qty', 'back_store_qty')
            ->orderBy('item_name')
            ->limit(30)
            ->get();

        return Inertia::render('Inventory/InterBranchTransfers/Create', [
            'siblingBranches' => $siblingBranches,
            'items'           => $items,
        ]);
    }

    /**
     * Store a newly created transfer.
     */
    public function store(Request $request, ?Branch $branch = null): RedirectResponse
    {
        $branch = $this->getBranch($request, $branch);
        $data = $request->validate([
            'destination_branch_id' => 'required|integer|exists:branches,id|different:branch_id',
            'from_location'         => 'required|string|in:back_store,front_store',
            'items'                 => 'required|array|min:1',
            'items.*.item_id'       => 'required|integer|exists:items,id',
            'items.*.quantity'      => 'required|integer|min:1',
            'items.*.notes'         => 'nullable|string|max:255',
            'notes'                 => 'nullable|string|max:500',
        ]);

        try {
            $transfer = InterBranchTransferService::createTransfer(
                $branch->id,
                (int) $data['destination_branch_id'],
                $data['items'],
                $data['from_location'],
                $data['notes'] ?? null,
                $request->user()?->id
            );

            $msg = $transfer->status === 'blocked'
                ? "Transfer created ({$transfer->reference}) but is BLOCKED due to unit mismatches. Stock is held until units align."
                : "Transfer {$transfer->reference} dispatched successfully. Stock is in transit.";

            return redirect()->route('pos.inventory.transfers.inter-branch.index', [$branch->slug, 'tab' => 'outgoing'])
                ->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Cancel a pending or blocked transfer (Sender only).
     */
    public function cancel(Request $request, mixed $param1 = null, mixed $param2 = null): RedirectResponse
    {
        $branch = $this->getBranch($request, ($param1 instanceof Branch ? $param1 : null));
        $transfer = ($param1 instanceof InterBranchTransfer) 
            ? $param1 
            : (($param2 instanceof InterBranchTransfer) ? $param2 : InterBranchTransfer::findOrFail($param2 ?? $param1));

        if ($transfer->source_branch_id !== $branch->id) {
            abort(403, 'Unauthorized. You can only cancel transfers dispatched by your branch.');
        }

        try {
            InterBranchTransferService::cancelTransfer($transfer, $request->user()?->id);
            return back()->with('success', "Transfer {$transfer->reference} cancelled. Stock restored to originating store.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Accept a pending transfer at destination branch.
     */
    public function accept(Request $request, mixed $param1 = null, mixed $param2 = null): RedirectResponse
    {
        $branch = $this->getBranch($request, ($param1 instanceof Branch ? $param1 : null));
        $transfer = ($param1 instanceof InterBranchTransfer) 
            ? $param1 
            : (($param2 instanceof InterBranchTransfer) ? $param2 : InterBranchTransfer::findOrFail($param2 ?? $param1));

        if ($transfer->destination_branch_id !== $branch->id) {
            abort(403, 'Unauthorized. You can only accept transfers sent to your branch.');
        }

        $data = $request->validate([
            'to_location'               => 'required|string|in:back_store,front_store',
            'items'                     => 'required|array|min:1',
            'items.*.line_id'           => 'required|integer|exists:inter_branch_transfer_items,id',
            'items.*.quantity_received' => 'required|integer|min:0',
            'items.*.link_action'       => 'nullable|string|in:existing,copy,none',
            'items.*.existing_item_id'  => 'nullable|integer|exists:items,id',
        ]);

        try {
            InterBranchTransferService::acceptTransfer(
                $transfer,
                $data['items'],
                $data['to_location'],
                $request->user()?->id
            );

            return back()->with('success', "Transfer {$transfer->reference} accepted successfully into " . ($data['to_location'] === 'back_store' ? 'Back Store' : 'Front Store') . ".");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
