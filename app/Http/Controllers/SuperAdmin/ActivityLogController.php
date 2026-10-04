<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PosLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ActivityLogController extends Controller
{
    private function streamExcelOrCsv(Spreadsheet $spreadsheet, string $filenamePrefix, string $format = 'xlsx')
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

    private function buildLogQuery(Request $request)
    {
        $query = PosLog::with([
            'branch:id,name,slug',
            'user:id,name,email,username',
        ])->latest('id');

        // Filter by branch
        if ($request->filled('branch_id')) {
            if ($request->branch_id === 'system') {
                $query->whereNull('branch_id');
            } else {
                $query->where('branch_id', $request->branch_id);
            }
        }

        // Filter by action type
        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Search text in log, ip, details
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
                  });
            });
        }

        return $query;
    }

    public function index(Request $request)
    {
        $query = $this->buildLogQuery($request);
        $logs = $query->paginate(25)->withQueryString();

        $branches = Branch::select('id', 'name', 'slug')->orderBy('name')->get();
        $users    = User::select('id', 'name', 'email')->orderBy('name')->get();

        $oldLogsCounts = [
            30 => PosLog::where('created_at', '<', now()->subDays(30))->count(),
            60 => PosLog::where('created_at', '<', now()->subDays(60))->count(),
            90 => PosLog::where('created_at', '<', now()->subDays(90))->count(),
        ];

        return Inertia::render('SuperAdmin/Logs/Index', [
            'logs'            => $logs,
            'branches'        => $branches,
            'users'           => $users,
            'filters'         => $request->only(['branch_id', 'action_type', 'user_id', 'search', 'start_date', 'end_date']),
            'old_logs_counts' => $oldLogsCounts,
        ]);
    }

    /**
     * Purge activity logs older than a specified number of days (e.g. 30, 60, 90).
     */
    public function prune(Request $request)
    {
        $validated = $request->validate([
            'days' => ['required', 'integer', 'in:30,60,90,180'],
        ]);

        $days = (int) $validated['days'];
        $cutoff = now()->subDays($days);

        $deletedCount = PosLog::where('created_at', '<', $cutoff)->delete();

        ActivityLogger::log(
            'system',
            "Super Admin cleared {$deletedCount} system activity log(s) older than {$days} days.",
            null,
            [
                'retention_days' => $days,
                'deleted_count'  => $deletedCount,
                'cutoff_date'    => $cutoff->toIso8601String(),
            ]
        );

        return back()->with('success', "Successfully deleted {$deletedCount} log record(s) older than {$days} days.");
    }

    /**
     * Export master activity and error logs in CSV or Excel format with date range and filters.
     */
    public function export(Request $request)
    {
        $format = $request->query('format', 'xlsx');
        $query = $this->buildLogQuery($request);
        $logs = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Activity & Error Logs');

        $headers = [
            '#',
            'Timestamp',
            'Category / Action',
            'Branch Location',
            'Staff / User',
            'Email / Username',
            'IP Address',
            'Log Description',
            'Payload / Error Details',
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
            ->getStartColor()->setRGB('059669'); // Emerald

        $rowNum = 2;
        foreach ($logs as $idx => $log) {
            $branchName = $log->branch ? $log->branch->name : 'Global System';
            $userName   = $log->user ? $log->user->name : 'System / Guest';
            $userEmail  = $log->user ? ($log->user->email ?: $log->user->username) : '-';
            $details    = is_array($log->details) ? json_encode($log->details, JSON_UNESCAPED_UNICODE) : ($log->details ?: '-');

            $col = 1;
            $sheet->setCellValue([$col++, $rowNum], $idx + 1);
            $sheet->setCellValue([$col++, $rowNum], $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '-');
            $sheet->setCellValue([$col++, $rowNum], strtoupper($log->action_type ?: 'INFO'));
            $sheet->setCellValue([$col++, $rowNum], $branchName);
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
            "Super Admin exported system activity & error logs ({$format}, " . count($logs) . " records)"
        );

        return $this->streamExcelOrCsv($spreadsheet, 'system_activity_logs', $format);
    }
}
