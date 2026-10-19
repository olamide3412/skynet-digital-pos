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
            ->with(['sourceBranch:id,name,slug', 'destinationBranch:id,name,slug', 'items.sourceItem', 'items.destinationItem', 'items.productGroup', 'sentByUser:id,name', 'acceptedByUser:id,name'])
            ->latest('id')
            ->paginate(15, ['*'], 'incoming_page');

        // Detect existing destination branch items for unlinked incoming transfer items
        $this->attachDestinationMatches($branch, $incoming->getCollection());

        // Outgoing Transfers (Sent)
        $outgoing = InterBranchTransfer::where('source_branch_id', $branch->id)
            ->with(['sourceBranch:id,name,slug', 'destinationBranch:id,name,slug', 'items.sourceItem', 'items.destinationItem', 'items.productGroup', 'sentByUser:id,name', 'acceptedByUser:id,name'])
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

        $transfer->load(['items.sourceItem', 'sourceBranch', 'destinationBranch']);

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

        $transfer->load(['items.sourceItem', 'items.destinationItem', 'items.productGroup', 'sourceBranch', 'destinationBranch']);

        $data = $request->validate([
            'to_location'               => 'required|string|in:back_store,front_store',
            'receiver_notes'            => 'nullable|string|max:500',
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
                $request->user()?->id,
                $data['receiver_notes'] ?? null
            );

            return redirect()->route('pos.inventory.transfers.inter-branch.index', [$branch->slug, 'tab' => 'incoming'])
                ->with('success', "Transfer {$transfer->reference} accepted successfully into " . ($data['to_location'] === 'back_store' ? 'Back Store' : 'Front Store') . ".");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Show full dedicated page for receiving and inspecting an incoming transfer.
     */
    public function showReceive(Request $request, mixed $param1 = null, mixed $param2 = null): Response|RedirectResponse
    {
        $branch = $this->getBranch($request, ($param1 instanceof Branch ? $param1 : null));
        $transfer = ($param1 instanceof InterBranchTransfer) 
            ? $param1 
            : (($param2 instanceof InterBranchTransfer) ? $param2 : InterBranchTransfer::findOrFail($param2 ?? $param1));

        if ($transfer->destination_branch_id !== $branch->id) {
            abort(403, 'Unauthorized. You can only receive transfers sent to your branch.');
        }

        if ($transfer->status !== 'pending') {
            return redirect()->route('pos.inventory.transfers.inter-branch.show', [$branch->slug, $transfer->id])
                ->with('info', "Transfer {$transfer->reference} is already {$transfer->status}.");
        }

        $transfer->load([
            'sourceBranch:id,name,slug',
            'destinationBranch:id,name,slug',
            'sentByUser:id,name',
            'items.sourceItem',
            'items.destinationItem',
            'items.productGroup',
        ]);

        $this->attachDestinationMatches($branch, [$transfer]);

        return Inertia::render('Inventory/InterBranchTransfers/Receive', [
            'transfer' => $transfer,
        ]);
    }

    /**
     * Show full details page for any transfer.
     */
    public function show(Request $request, mixed $param1 = null, mixed $param2 = null): Response
    {
        $branch = $this->getBranch($request, ($param1 instanceof Branch ? $param1 : null));
        $transfer = ($param1 instanceof InterBranchTransfer) 
            ? $param1 
            : (($param2 instanceof InterBranchTransfer) ? $param2 : InterBranchTransfer::findOrFail($param2 ?? $param1));

        if ($transfer->source_branch_id !== $branch->id && $transfer->destination_branch_id !== $branch->id) {
            abort(403, 'Unauthorized.');
        }

        $transfer->load([
            'sourceBranch:id,name,slug',
            'destinationBranch:id,name,slug',
            'sentByUser:id,name',
            'acceptedByUser:id,name',
            'cancelledByUser:id,name',
            'items.sourceItem',
            'items.destinationItem',
            'items.productGroup',
        ]);

        return Inertia::render('Inventory/InterBranchTransfers/Show', [
            'transfer'   => $transfer,
            'isReceiver' => $transfer->destination_branch_id === $branch->id,
        ]);
    }

    /**
     * Attach auto-detected destination branch items to unlinked transfer item lines.
     */
    protected function attachDestinationMatches(Branch $branch, iterable $transfers): void
    {
        $unlinkedLines = collect();
        foreach ($transfers as $t) {
            if ($t->status !== 'pending') {
                continue;
            }
            foreach ($t->items as $itemLine) {
                if (!$itemLine->destination_item_id && $itemLine->sourceItem) {
                    $unlinkedLines->push($itemLine);
                }
            }
        }

        if ($unlinkedLines->isNotEmpty()) {
            $barcodes = $unlinkedLines->pluck('sourceItem.barcode_number')
                ->filter(fn($b) => !empty(trim($b)))
                ->unique()
                ->values()
                ->all();

            $names = $unlinkedLines->pluck('sourceItem.item_name')
                ->filter(fn($n) => !empty(trim($n)))
                ->map(fn($n) => strtolower(trim($n)))
                ->unique()
                ->values()
                ->all();

            $destItems = Item::where('branch_id', $branch->id)
                ->where(function ($q) use ($barcodes, $names) {
                    $hasClause = false;
                    if (!empty($barcodes)) {
                        $q->whereIn('barcode_number', $barcodes);
                        $hasClause = true;
                    }
                    if (!empty($names)) {
                        if ($hasClause) {
                            $q->orWhere(function ($sub) use ($names) {
                                foreach ($names as $name) {
                                    $sub->orWhereRaw('LOWER(TRIM(item_name)) = ?', [$name]);
                                }
                            });
                        } else {
                            $q->where(function ($sub) use ($names) {
                                foreach ($names as $name) {
                                    $sub->orWhereRaw('LOWER(TRIM(item_name)) = ?', [$name]);
                                }
                            });
                        }
                    }
                })
                ->get();

            $barcodeMap = [];
            $nameMap = [];
            foreach ($destItems as $di) {
                $b = trim($di->barcode_number ?? '');
                if ($b !== '') {
                    $barcodeMap[$b] = $di;
                }
                $n = strtolower(trim($di->item_name ?? ''));
                if ($n !== '') {
                    $nameMap[$n] = $di;
                }
            }

            foreach ($unlinkedLines as $line) {
                $srcBarcode = trim($line->sourceItem->barcode_number ?? '');
                $srcName = strtolower(trim($line->sourceItem->item_name ?? ''));

                $matched = null;
                $matchType = null;

                if ($srcBarcode !== '' && isset($barcodeMap[$srcBarcode])) {
                    $matched = $barcodeMap[$srcBarcode];
                    $matchType = 'barcode';
                } elseif ($srcName !== '' && isset($nameMap[$srcName])) {
                    $matched = $nameMap[$srcName];
                    $matchType = 'name';
                }

                if ($matched) {
                    $line->setAttribute('matched_destination_item', [
                        'id'              => $matched->id,
                        'item_name'       => $matched->item_name,
                        'barcode_number'  => $matched->barcode_number,
                        'unit_label'      => $matched->unit_label ?: 'pcs',
                        'front_store_qty' => (int) $matched->front_store_qty,
                        'back_store_qty'  => (int) $matched->back_store_qty,
                        'match_type'      => $matchType,
                    ]);
                } else {
                    $line->setAttribute('matched_destination_item', null);
                }
            }
        }

        foreach ($transfers as $t) {
            foreach ($t->items as $itemLine) {
                if (!$itemLine->offsetExists('matched_destination_item')) {
                    $itemLine->setAttribute('matched_destination_item', null);
                }
            }
        }
    }
}
