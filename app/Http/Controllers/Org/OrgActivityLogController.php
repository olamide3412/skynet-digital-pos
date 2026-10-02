<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\PosLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrgActivityLogController extends Controller
{
    /**
     * Resolve the active organization for the current user session.
     */
    protected function resolveOrganization(Request $request): ?Organization
    {
        $user = Auth::guard('web')->user();

        if (!$user) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            if ($request->has('org_id')) {
                return Organization::find($request->get('org_id')) ?: Organization::first();
            }
            return $user->organization_id ? Organization::find($user->organization_id) : Organization::first();
        }

        return $user->organization_id ? Organization::find($user->organization_id) : null;
    }

    /**
     * Helper to stream spreadsheet as Excel (.xlsx) or CSV (.csv).
     */
    private function streamSpreadsheet(Spreadsheet $spreadsheet, string $filenamePrefix, string $format = 'xlsx'): StreamedResponse
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
     * Build the scoped PosLog query for this organization, strictly excluding any SuperAdmin logs.
     */
    protected function buildScopedQuery(Organization $org, Request $request)
    {
        // 1. All branch IDs belonging to this organization
        $orgBranchIds = Branch::where('organization_id', $org->id)->pluck('id')->all();

        // 2. Base query: strictly scoped to branches in this organization
        $query = PosLog::whereIn('branch_id', $orgBranchIds)
            // 3. CRITICAL: Strictly hide all Superadmin logs
            ->whereDoesntHave('user', function ($uq) {
                $uq->where('is_super_admin', true);
            })
            ->where(function ($q) {
                $q->whereNull('log')
                  ->orWhere(function ($sq) {
                      $sq->where('log', 'not like', '%Super Admin%')
                         ->where('log', 'not like', '%Platform Super Admin%');
                  });
            });

        // 4. Filter by specific branch if chosen
        if ($request->filled('branch_id') && in_array((int) $request->branch_id, $orgBranchIds, true)) {
            $query->where('branch_id', (int) $request->branch_id);
        }

        // 5. Filter by action type
        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        // 6. Filter by staff user
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }

        // 7. Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // 8. Keyword search
        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('log', 'like', $search)
                  ->orWhere('action_type', 'like', $search)
                  ->orWhere('ip_address', 'like', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', $search)
                         ->orWhere('email', 'like', $search)
                         ->orWhere('username', 'like', $search);
                  })
                  ->orWhereHas('branch', function ($bq) use ($search) {
                      $bq->where('name', 'like', $search)
                         ->orWhere('slug', 'like', $search);
                  });
            });
        }

        return $query->with([
            'branch:id,name,slug',
            'user:id,name,email,username',
        ])->latest('id');
    }

    /**
     * Display activity logs in the Organization Portal.
     */
    public function index(Request $request): Response
    {
        $org = $this->resolveOrganization($request);
        if (!$org) {
            abort(404, 'No organization found.');
        }

        $branches = Branch::where('organization_id', $org->id)
            ->select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();

        $orgBranchIds = $branches->pluck('id')->all();

        // Staff members of this organization (excluding super admins)
        $users = User::where('organization_id', $org->id)
            ->where('is_super_admin', false)
            ->select('id', 'name', 'username', 'email')
            ->orderBy('name')
            ->get();

        // Available action types in this organization's logs
        $actionTypes = PosLog::whereIn('branch_id', $orgBranchIds)
            ->whereNotNull('action_type')
            ->distinct()
            ->pluck('action_type')
            ->sort()
            ->values();

        $query = $this->buildScopedQuery($org, $request);
        $logs = $query->paginate(25)->withQueryString();

        return Inertia::render('Org/ActivityLogs/Index', [
            'organization' => [
                'id'   => $org->id,
                'name' => $org->name,
            ],
            'logs'        => $logs,
            'branches'    => $branches,
            'users'       => $users,
            'actionTypes' => $actionTypes,
            'filters'     => $request->only(['branch_id', 'action_type', 'user_id', 'start_date', 'end_date', 'search']),
        ]);
    }

    /**
     * Export branch activity logs in Excel or CSV format.
     */
    public function export(Request $request): StreamedResponse
    {
        $org = $this->resolveOrganization($request);
        if (!$org) {
            abort(404, 'No organization found.');
        }

        $format = strtolower($request->query('format', 'xlsx')) === 'csv' ? 'csv' : 'xlsx';
        $query = $this->buildScopedQuery($org, $request);
        $logs = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Branch Activity Logs');

        $headers = [
            '#',
            'Timestamp',
            'Branch Location',
            'Category / Action',
            'Staff Member',
            'Email / Username',
            'IP Address',
            'Activity Description',
            'Metadata / Payload',
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
            ->getStartColor()->setRGB('7C3AED'); // Purple (matching Org Portal)

        $rowNum = 2;
        foreach ($logs as $idx => $log) {
            $branchName = $log->branch ? $log->branch->name : 'Organization';
            $userName   = $log->user ? $log->user->name : 'System';
            $userEmail  = $log->user ? ($log->user->email ?: $log->user->username) : '-';
            $details    = is_array($log->details) ? json_encode($log->details, JSON_UNESCAPED_UNICODE) : ($log->details ?: '-');

            $col = 1;
            $sheet->setCellValue([$col++, $rowNum], $idx + 1);
            $sheet->setCellValue([$col++, $rowNum], $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '-');
            $sheet->setCellValue([$col++, $rowNum], $branchName);
            $sheet->setCellValue([$col++, $rowNum], strtoupper($log->action_type ?: 'INFO'));
            $sheet->setCellValue([$col++, $rowNum], $userName);
            $sheet->setCellValue([$col++, $rowNum], $userEmail);
            $sheet->setCellValue([$col++, $rowNum], $log->ip_address ?: '-');
            $sheet->setCellValue([$col++, $rowNum], $log->log);
            $sheet->setCellValue([$col++, $rowNum], $details);

            $rowNum++;
        }

        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        ActivityLogger::log(
            'system',
            "Organization Admin exported activity logs ({$format}, " . count($logs) . " records)",
            null,
            null,
            Auth::id()
        );

        $orgSlug = \Illuminate\Support\Str::slug($org->name);
        return $this->streamSpreadsheet($spreadsheet, "{$orgSlug}_activity_logs", $format);
    }
}
