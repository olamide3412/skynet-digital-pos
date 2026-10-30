<?php

namespace App\Modules\Laundry\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PosCustomer;
use App\Modules\Laundry\Models\LaundryCategory;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Models\LaundryService;
use App\Modules\Laundry\Models\LaundrySetting;
use App\Modules\Laundry\Services\LaundryDiscountService;
use App\Modules\Laundry\Services\LaundryOrderService;
use App\Services\RoleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class LaundryOrderController extends Controller
{
    public function __construct(
        protected LaundryOrderService $orderService
    ) {}

    /**
     * Format a laundry order model into a standardized array payload.
     */
    protected function transformOrder(LaundryOrder $o, int $unclaimedAfterDays = 14): array
    {
        return [
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
            'items_summary'  => $o->items->map(function ($i) {
                $q = (float) $i->quantity;
                $qty = ($q == (int) $q) ? (int) $q : $q;
                return "{$qty}x {$i->service_name}";
            })->join(', '),
            'is_overdue'     => $o->isOverdue(),
            'is_unclaimed'   => $o->isUnclaimed($unclaimedAfterDays),
            'note'           => $o->note,
        ];
    }

    /**
     * Orders list data table view.
     */
    public function index(Request $request)
    {
        $branch = current_branch();

        // Graceful redirect if view=board requested on /orders
        if ($request->query('view') === 'board') {
            return redirect()->route('laundry.board.index', array_merge(['branch' => $branch->slug], $request->except('view')));
        }

        $search = $request->query('search', '');
        $status = $request->query('status', '');
        $filterOverdue = $request->boolean('overdue');
        $filterDueToday = $request->boolean('due_today');
        $filterUnclaimed = $request->boolean('unclaimed');
        $filterUnpaid = $request->boolean('unpaid');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $settings = LaundrySetting::forBranch($branch->id);
        $unclaimedThreshold = Carbon::now()->subDays($settings->unclaimed_after_days ?: 14);

        $query = $this->getFilteredOrdersQuery($request, $branch, $unclaimedThreshold);

        $counts = [
            'all'         => LaundryOrder::where('branch_id', $branch->id)->count(),
            'active'      => LaundryOrder::where('branch_id', $branch->id)->whereIn('status', ['received', 'in_progress'])->count(),
            'ready'       => LaundryOrder::where('branch_id', $branch->id)->where('status', 'ready')->count(),
            'collected'   => LaundryOrder::where('branch_id', $branch->id)->where('status', 'collected')->count(),
            'cancelled'   => LaundryOrder::where('branch_id', $branch->id)->where('status', 'cancelled')->count(),
            'overdue'     => LaundryOrder::where('branch_id', $branch->id)->whereNotIn('status', ['collected', 'cancelled'])->where('due_at', '<', Carbon::now())->count(),
            'due_today'   => LaundryOrder::where('branch_id', $branch->id)->whereNotIn('status', ['collected', 'cancelled'])->whereDate('due_at', Carbon::today())->count(),
            'unpaid'      => LaundryOrder::where('branch_id', $branch->id)->where('balance', '>', 0)->count(),
            'unclaimed'   => LaundryOrder::where('branch_id', $branch->id)->where('status', 'ready')->where('due_at', '<=', $unclaimedThreshold)->count(),
        ];

        $orders = (clone $query)
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn($o) => $this->transformOrder($o, $settings->unclaimed_after_days ?: 14));

        return Inertia::render('Laundry/Orders/Index', [
            'orders'   => $orders,
            'counts'   => $counts,
            'filters'  => [
                'search'     => $search,
                'status'     => $status,
                'overdue'    => $filterOverdue,
                'due_today'  => $filterDueToday,
                'unclaimed'  => $filterUnclaimed,
                'unpaid'     => $filterUnpaid,
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ],
            'settings' => $settings,
        ]);
    }

    /**
     * Build the filtered query for laundry orders based on the request parameters.
     */
    protected function getFilteredOrdersQuery(Request $request, $branch, Carbon $unclaimedThreshold)
    {
        $search = $request->query('search', '');
        $status = $request->query('status', '');
        $filterOverdue = $request->boolean('overdue');
        $filterDueToday = $request->boolean('due_today');
        $filterUnclaimed = $request->boolean('unclaimed');
        $filterUnpaid = $request->boolean('unpaid');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        return LaundryOrder::where('branch_id', $branch->id)
            ->with(['items', 'customer', 'creator', 'collectedBy', 'payments'])
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
            ->when($filterUnclaimed, function ($q) use ($unclaimedThreshold) {
                $q->where('status', 'ready')->where('due_at', '<=', $unclaimedThreshold);
            })
            ->when($filterUnpaid, fn($q) => $q->where('balance', '>', 0))
            ->when($startDate, fn($q) => $q->whereDate('received_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('received_at', '<=', $endDate));
    }

    /**
     * Export laundry orders as Excel or CSV spreadsheet.
     */
    public function export(Request $request)
    {
        $branch = current_branch();
        $settings = LaundrySetting::forBranch($branch->id);
        $unclaimedThreshold = Carbon::now()->subDays($settings->unclaimed_after_days ?: 14);

        $orders = $this->getFilteredOrdersQuery($request, $branch, $unclaimedThreshold)
            ->latest('id')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laundry Orders');

        $headers = [
            '#',
            'Order Number',
            'Date Received',
            'Due Date',
            'Collection Date',
            'Customer Name',
            'Customer Phone',
            'Garments Count',
            'Garments Summary',
            'Status',
            'Payment Status',
            'Subtotal (₦)',
            'Discount (₦)',
            'Tax (₦)',
            'Total (₦)',
            'Amount Paid (₦)',
            'Balance Due (₦)',
            'Intake Staff',
            'Collected By',
            'Notes',
        ];

        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4F46E5'); // Indigo brand color

        $rowNum = 2;
        foreach ($orders as $idx => $o) {
            $custName = $o->customer_name ?: ($o->customer?->name ?: 'Walk-in Customer');
            $custPhone = $o->customer_phone ?: ($o->customer?->phone ?: '-');
            $creatorName = $o->creator?->name ?: ($o->creator?->full_name ?: '-');
            $collectedByName = $o->collected_by_name ?: ($o->collectedBy?->name ?: '-');

            $garmentsCount = (int) ($o->items->sum('quantity') ?: $o->items->count());
            $garmentsSummary = $o->items->map(function ($i) {
                $q = (float) $i->quantity;
                $qty = ($q == (int) $q) ? (int) $q : $q;
                return "{$qty}x {$i->service_name}";
            })->join(', ') ?: '-';

            $statusText = match ($o->status) {
                'received'    => 'Received',
                'in_progress' => 'In Progress',
                'ready'       => 'Ready for Collection',
                'collected'   => 'Collected',
                'cancelled'   => 'Cancelled',
                default       => ucfirst(str_replace('_', ' ', (string) $o->status)),
            };

            $total = (float) $o->total;
            $amountPaid = (float) $o->amount_paid;
            $balance = (float) $o->balance;

            if ($balance <= 0) {
                $paymentStatus = 'Fully Paid';
            } elseif ($amountPaid > 0) {
                $paymentStatus = 'Partial Payment';
            } else {
                $paymentStatus = 'Unpaid';
            }

            $col = 1;
            $sheet->setCellValue([$col++, $rowNum], $idx + 1);
            $sheet->setCellValue([$col++, $rowNum], $o->order_number ?: '-');
            $sheet->setCellValue([$col++, $rowNum], $o->received_at ? $o->received_at->format('Y-m-d H:i') : '-');
            $sheet->setCellValue([$col++, $rowNum], $o->due_at ? $o->due_at->format('Y-m-d H:i') : '-');
            $sheet->setCellValue([$col++, $rowNum], $o->collected_at ? $o->collected_at->format('Y-m-d H:i') : '-');
            $sheet->setCellValue([$col++, $rowNum], $custName);
            $sheet->setCellValue([$col++, $rowNum], $custPhone);
            $sheet->setCellValue([$col++, $rowNum], $garmentsCount);
            $sheet->setCellValue([$col++, $rowNum], $garmentsSummary);
            $sheet->setCellValue([$col++, $rowNum], $statusText);
            $sheet->setCellValue([$col++, $rowNum], $paymentStatus);
            $sheet->setCellValue([$col++, $rowNum], (float) $o->subtotal);
            $sheet->setCellValue([$col++, $rowNum], (float) $o->discount_amount);
            $sheet->setCellValue([$col++, $rowNum], (float) $o->tax_amount);
            $sheet->setCellValue([$col++, $rowNum], $total);
            $sheet->setCellValue([$col++, $rowNum], $amountPaid);
            $sheet->setCellValue([$col++, $rowNum], $balance);
            $sheet->setCellValue([$col++, $rowNum], $creatorName);
            $sheet->setCellValue([$col++, $rowNum], $collectedByName);
            $sheet->setCellValue([$col++, $rowNum], $o->note ?: '-');

            $rowNum++;
        }

        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        return $this->streamExcelOrCsv($spreadsheet, 'laundry_orders_' . $branch->slug, $request->get('format', 'xlsx'));
    }

    /**
     * Stream spreadsheet download as either XLSX or CSV.
     */
    protected function streamExcelOrCsv(Spreadsheet $spreadsheet, string $filenamePrefix, string $format = 'xlsx')
    {
        $format = strtolower($format) === 'csv' ? 'csv' : 'xlsx';
        $filename = $filenamePrefix . '_' . date('Y-m-d_His') . '.' . $format;

        if ($format === 'csv') {
            $writer = new CsvWriter($spreadsheet);
            $writer->setUseBOM(true);
            $writer->setPreCalculateFormulas(false);
            $contentType = 'text/csv; charset=UTF-8';
        } else {
            $writer = new XlsxWriter($spreadsheet);
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * Orders workflow Kanban board view.
     */
    public function board(Request $request)
    {
        $branch = current_branch();

        // Graceful redirect if view=list requested on /board
        if ($request->query('view') === 'list') {
            return redirect()->route('laundry.orders.index', array_merge(['branch' => $branch->slug], $request->except('view')));
        }

        $search = $request->query('search', '');
        $filterOverdue = $request->boolean('overdue');
        $filterDueToday = $request->boolean('due_today');
        $filterUnclaimed = $request->boolean('unclaimed');
        $filterUnpaid = $request->boolean('unpaid');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $settings = LaundrySetting::forBranch($branch->id);
        $unclaimedThreshold = Carbon::now()->subDays($settings->unclaimed_after_days ?: 14);

        $query = LaundryOrder::where('branch_id', $branch->id)
            ->with(['items', 'customer'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('order_number', 'like', "%{$search}%")
                       ->orWhere('customer_name', 'like', "%{$search}%")
                       ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            })
            ->when($filterOverdue, function ($q) {
                $q->whereNotIn('status', ['collected', 'cancelled'])
                  ->where('due_at', '<', Carbon::now());
            })
            ->when($filterDueToday, function ($q) {
                $q->whereDate('due_at', Carbon::today())
                  ->whereNotIn('status', ['collected', 'cancelled']);
            })
            ->when($filterUnclaimed, function ($q) use ($unclaimedThreshold) {
                $q->where('status', 'ready')->where('due_at', '<=', $unclaimedThreshold);
            })
            ->when($filterUnpaid, fn($q) => $q->where('balance', '>', 0))
            ->when($startDate, fn($q) => $q->whereDate('received_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('received_at', '<=', $endDate));

        $boardCounts = [
            'all'         => (clone $query)->count(),
            'received'    => (clone $query)->where('status', 'received')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'ready'       => (clone $query)->where('status', 'ready')->count(),
            'overdue'     => LaundryOrder::where('branch_id', $branch->id)->whereNotIn('status', ['collected', 'cancelled'])->where('due_at', '<', Carbon::now())->count(),
            'due_today'   => LaundryOrder::where('branch_id', $branch->id)->whereNotIn('status', ['collected', 'cancelled'])->whereDate('due_at', Carbon::today())->count(),
            'unpaid'      => LaundryOrder::where('branch_id', $branch->id)->where('balance', '>', 0)->count(),
            'unclaimed'   => LaundryOrder::where('branch_id', $branch->id)->where('status', 'ready')->where('due_at', '<=', $unclaimedThreshold)->count(),
        ];

        $boardOrders = [];
        $columns = ['received', 'in_progress', 'ready'];
        foreach ($columns as $colStatus) {
            $items = (clone $query)->where('status', $colStatus)
                ->orderBy('due_at')
                ->orderBy('id')
                ->limit(10)
                ->get();

            $boardOrders[$colStatus] = $items->map(fn($o) => $this->transformOrder($o, $settings->unclaimed_after_days ?: 14))->values()->all();
        }

        return Inertia::render('Laundry/Orders/Board', [
            'board_orders' => $boardOrders,
            'board_counts' => $boardCounts,
            'filters'      => [
                'search'     => $search,
                'overdue'    => $filterOverdue,
                'due_today'  => $filterDueToday,
                'unclaimed'  => $filterUnclaimed,
                'unpaid'     => $filterUnpaid,
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ],
            'settings'     => $settings,
        ]);
    }

    /**
     * Asynchronously load next page of orders for a specific Kanban column.
     */
    public function columnOrders(Request $request)
    {
        $branch = current_branch();
        $status = $request->query('status', 'received');
        $page = max(1, (int) $request->query('page', 2));
        $perPage = (int) $request->query('per_page', 10);
        $search = $request->query('search', '');
        $filterOverdue = $request->boolean('overdue');
        $filterDueToday = $request->boolean('due_today');
        $filterUnclaimed = $request->boolean('unclaimed');
        $filterUnpaid = $request->boolean('unpaid');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $settings = LaundrySetting::forBranch($branch->id);

        $query = LaundryOrder::where('branch_id', $branch->id)
            ->where('status', $status)
            ->with(['items', 'customer'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('order_number', 'like', "%{$search}%")
                       ->orWhere('customer_name', 'like', "%{$search}%")
                       ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            })
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

        $total = (clone $query)->count();

        $items = $query->orderBy('due_at')
            ->orderBy('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $formatted = $items->map(fn($o) => $this->transformOrder($o, $settings->unclaimed_after_days ?: 14))->values()->all();

        return response()->json([
            'status'   => $status,
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
            'has_more' => ($page * $perPage) < $total,
            'orders'   => $formatted,
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
            ->with('category:id,name,slug')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'branch_id', 'category_id', 'name', 'pricing_type', 'price'])
            ->map(function ($s) {
                $discountInfo = LaundryDiscountService::resolveServiceDiscount($s);
                return [
                    'id'               => $s->id,
                    'branch_id'        => $s->branch_id,
                    'category_id'      => $s->category_id,
                    'category_name'    => $s->category?->name,
                    'name'             => $s->name,
                    'pricing_type'     => $s->pricing_type,
                    'price'            => (float) $s->price,
                    'has_discount'     => $discountInfo['has_discount'],
                    'discounted_price' => $discountInfo['discounted_price'],
                    'discount_amount'  => $discountInfo['discount_amount'],
                    'discount_type'    => $discountInfo['discount_type'],
                    'discount_value'   => $discountInfo['discount_value'],
                    'discount_name'    => $discountInfo['discount_name'],
                    'discount_id'      => $discountInfo['discount_id'],
                ];
            });

        $categories = LaundryCategory::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name');

        $activeGeneralDiscount = LaundryDiscountService::getActiveGeneralDiscount($branch->id);

        $activeTillSession = null;
        $user = auth()->user();
        if ($user && $branch) {
            $session = \App\Services\TillSessionService::getActiveSession($user->id, $branch->id);
            if ($session) {
                $session->load('cashMovements.performer:id,name');
                $activeTillSession = array_merge($session->toArray(), [
                    'summary' => $session->calculateSummary(),
                ]);
            }
        }

        return Inertia::render('Laundry/Orders/Create', [
            'services'              => $services,
            'categories'            => $categories,
            'activeGeneralDiscount' => $activeGeneralDiscount ? [
                'id'             => $activeGeneralDiscount->id,
                'name'           => $activeGeneralDiscount->name,
                'discount_type'  => $activeGeneralDiscount->discount_type,
                'discount_value' => (float) $activeGeneralDiscount->discount_value,
                'starts_at'      => $activeGeneralDiscount->starts_at?->format('Y-m-d H:i'),
                'ends_at'        => $activeGeneralDiscount->ends_at?->format('Y-m-d H:i'),
            ] : null,
            'canApplyDiscount'          => RoleService::canApplyDiscount(),
            'canOverridePrice'          => RoleService::canOverrideLaundryPrice() || ($user?->isBranchAdmin() ?? false) || ($user?->isSuperAdmin() ?? false),
            'activeTillSession'         => $activeTillSession,
            'canManageTillCashMovements' => RoleService::canManageTillCashMovements() || ($user?->isBranchAdmin() ?? false) || ($user?->isSuperAdmin() ?? false),
            'settings'              => [
                'default_turnaround_days' => $laundrySettings->default_turnaround_days ?: 2,
                'order_prefix'            => $laundrySettings->order_prefix ?: 'LDY-',
                'is_tax_enabled'          => (bool) $posSettings->is_tax_enabled,
                'tax_percentage'          => (float) ($posSettings->tax_percentage ?? 0),
                'allow_price_override'    => (bool) $laundrySettings->allow_price_override,
                'require_till_opening'    => (bool) ($posSettings->require_till_opening ?? false),
                'print_till_slip'         => (bool) ($posSettings->print_till_slip ?? true),
            ],
        ]);
    }

    /**
     * Store new order in atomic transaction.
     */
    public function store(Request $request)
    {
        $branch = current_branch();

        if ($request->has('deposit_amount') && is_string($request->input('deposit_amount'))) {
            $request->merge([
                'deposit_amount' => (float) str_replace(',', '', $request->input('deposit_amount')),
            ]);
        }

        $validated = $request->validate([
            'customer_id'       => 'nullable|integer',
            // Quick customer creation if customer_id not present
            'customer_name'     => 'required_without:customer_id|nullable|string|max:255',
            'customer_phone'    => 'required_without:customer_id|nullable|string|max:50',
            'customer_address'  => 'nullable|string|max:500',
            'customer_email'    => 'nullable|email|max:255',

            'items'                       => 'required|array|min:1',
            'items.*.service_id'          => 'nullable|integer',
            'items.*.service_name'        => 'required|string|max:255',
            'items.*.pricing_type'        => 'required|string|in:per_piece,per_kg',
            'items.*.original_price'      => 'nullable|numeric|min:0',
            'items.*.unit_price'          => 'required|numeric|min:0',
            'items.*.quantity'            => 'required|numeric|min:0.01',
            'items.*.discount_amount'     => 'nullable|numeric|min:0',
            'items.*.laundry_discount_id' => 'nullable|integer',
            'items.*.note'                => 'nullable|string|max:500',

            'due_at'              => 'nullable|date',
            'discount_amount'     => 'nullable|numeric|min:0',
            'discount_type'       => 'nullable|string|in:percentage,fixed',
            'laundry_discount_id' => 'nullable|integer',
            'deposit_amount'      => 'nullable|numeric|min:0',
            'payment_method'      => 'nullable|string|max:50',
            'payment_reference'   => 'nullable|string|max:100',
            'note'                => 'nullable|string|max:1000',
        ]);

        // If creating customer inline
        if (empty($validated['customer_id'])) {
            $customer = PosCustomer::create([
                'branch_id' => $branch->id,
                'name'      => trim($validated['customer_name']),
                'phone'     => trim($validated['customer_phone']),
                'address'   => !empty($validated['customer_address']) ? trim($validated['customer_address']) : 'NA',
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
            'items.discount',
            'generalDiscount',
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

        $activeTillSession = null;
        $user = auth()->user();
        $posSettings = $branch->getSettings();
        if ($user && $branch) {
            $session = \App\Services\TillSessionService::getActiveSession($user->id, $branch->id);
            if ($session) {
                $session->load('cashMovements.performer:id,name');
                $activeTillSession = array_merge($session->toArray(), [
                    'summary' => $session->calculateSummary(),
                ]);
            }
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
                'collected_by_staff_name' => $order->collectedBy?->name ?? 'Staff',
                'created_by_name'      => $order->creator?->name ?? 'Staff',
                'subtotal'             => (float) $order->subtotal,
                'discount_amount'      => (float) $order->discount_amount,
                'discount_type'        => $order->discount_type,
                'laundry_discount_id'  => $order->laundry_discount_id,
                'general_discount_name'=> $order->generalDiscount?->name,
                'tax_amount'           => (float) $order->tax_amount,
                'total'                => (float) $order->total,
                'amount_paid'          => (float) $order->amount_paid,
                'balance'              => (float) $order->balance,
                'note'                 => $order->note,
                'is_overdue'           => $order->isOverdue(),
                'is_unclaimed'         => $order->isUnclaimed($settings->unclaimed_after_days ?: 14),
                'items'                => $order->items->map(fn($i) => [
                    'id'                  => $i->id,
                    'service_name'        => $i->service_name,
                    'pricing_type'        => $i->pricing_type,
                    'original_price'      => (float) ($i->original_price ?: $i->unit_price),
                    'unit_price'          => (float) $i->unit_price,
                    'quantity'            => ($q = (float) $i->quantity) == (int) $q ? (int) $q : $q,
                    'discount_amount'     => (float) ($i->discount_amount ?? 0),
                    'discount_name'       => $i->discount?->name,
                    'has_discount'        => (float) ($i->discount_amount ?? 0) > 0,
                    'line_total'          => (float) $i->line_total,
                    'note'                => $i->note,
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
                    'collected_by_name' => ($h->to_status === 'collected') ? $order->collected_by_name : null,
                ]),
            ],
            'whatsapp_url'  => $whatsAppUrl,
            'branch'        => [
                'name'    => $branch->name,
                'address' => $branch->address,
                'phone'   => $branch->phone,
            ],
            'settings'      => array_merge($settings->toArray(), [
                'require_till_opening' => (bool) ($posSettings->require_till_opening ?? false),
                'print_till_slip'      => (bool) ($posSettings->print_till_slip ?? true),
                'allow_unpaid_pickup'  => (bool) $settings->allow_unpaid_pickup,
                'allow_price_override' => (bool) $settings->allow_price_override,
            ]),
            'canReleaseUnpaid'           => $user ? ($user->isSuperAdmin() || $user->isBranchAdmin() || $user->hasPermissionTo('laundry.release_unpaid') || (bool) $settings->allow_unpaid_pickup) : false,
            'activeTillSession'          => $activeTillSession,
            'canManageTillCashMovements' => RoleService::canManageTillCashMovements() || ($user?->isBranchAdmin() ?? false) || ($user?->isSuperAdmin() ?? false),
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
        if ($request->has('amount') && is_string($request->input('amount'))) {
            $request->merge([
                'amount' => (float) str_replace(',', '', $request->input('amount')),
            ]);
        }

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
        if ($request->has('refund_amount') && is_string($request->input('refund_amount'))) {
            $request->merge([
                'refund_amount' => (float) str_replace(',', '', $request->input('refund_amount')),
            ]);
        }

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
                    'qty'         => ($q = (float) $i->quantity) == (int) $q ? (int) $q : $q,
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
     * Strictly scoped to the current branch.
     */
    public function searchCustomers(Request $request)
    {
        $branchParam = $request->route('branch');
        $branch = current_branch();
        if (!$branch && $branchParam) {
            $branch = $branchParam instanceof \App\Models\Branch ? $branchParam : \App\Models\Branch::where('slug', (string) $branchParam)->first();
        }
        if (!$branch) {
            $branch = \Illuminate\Support\Facades\Auth::guard('web')->user()?->branch;
        }

        $query = trim($request->input('query', $request->input('q', $request->input('search', ''))));

        if (strlen($query) < 1) {
            return response()->json([]);
        }

        if (!$branch) {
            return response()->json([]);
        }

        $customers = PosCustomer::query()
            ->where('branch_id', $branch->id)
            ->search($query)
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'whatsapp_number', 'email', 'address', 'debt_bal', 'watch_list', 'branch_id']);

        return response()->json($customers);
    }

    /**
     * Quick-create a customer from laundry intake form.
     */
    public function quickCreateCustomer(Request $request)
    {
        $branchParam = $request->route('branch');
        $branch = current_branch();
        if (!$branch && $branchParam) {
            $branch = $branchParam instanceof \App\Models\Branch ? $branchParam : \App\Models\Branch::where('slug', (string) $branchParam)->first();
        }
        if (!$branch) {
            $branch = \Illuminate\Support\Facades\Auth::guard('web')->user()?->branch;
        }

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'phone'           => 'required|string|max:50',
            'whatsapp_number' => 'nullable|string|max:50',
            'same_as_phone'   => 'nullable|boolean',
            'email'           => 'nullable|email|max:255',
            'address'         => 'nullable|string|max:500',
        ]);

        $phone = trim($validated['phone']);

        $sameAsPhone = filter_var($request->input('same_as_phone', false), FILTER_VALIDATE_BOOLEAN);
        $whatsappNumber = ($sameAsPhone || empty($validated['whatsapp_number']))
            ? $phone
            : trim($validated['whatsapp_number']);

        // Check if phone already exists in this branch only (duplication avoided only on branch level)
        $existing = PosCustomer::where('phone', $phone)
            ->when($branch, function ($q) use ($branch) {
                $q->where('branch_id', $branch->id);
            })
            ->first();

        if ($existing) {
            return response()->json([
                'duplicate' => true,
                'message'   => "A customer in this branch already exists with phone {$phone} ({$existing->name}).",
                'customer'  => $existing,
            ], 200);
        }

        $email = !empty($validated['email']) ? trim($validated['email']) : null;
        if ($email) {
            $existingEmail = PosCustomer::where('email', $email)
                ->when($branch, function ($q) use ($branch) {
                    $q->where('branch_id', $branch->id);
                })
                ->first();

            if ($existingEmail) {
                return response()->json([
                    'duplicate' => true,
                    'message'   => "A customer in this branch already exists with email {$email} ({$existingEmail->name}).",
                    'customer'  => $existingEmail,
                ], 200);
            }
        }

        $customer = PosCustomer::create([
            'branch_id'       => $branch?->id,
            'name'            => trim($validated['name']),
            'phone'           => $phone,
            'whatsapp_number' => $whatsappNumber,
            'email'           => $email,
            'address'         => !empty($validated['address']) ? trim($validated['address']) : 'NA',
            'debt_bal'        => 0,
        ]);

        return response()->json([
            'duplicate' => false,
            'customer'  => $customer,
        ], 201);
    }
}
