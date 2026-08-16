<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PrinterSetupController extends Controller
{
    /**
     * Display the workstation printer setup page.
     * Accessible by all branch staff.
     */
    public function index()
    {
        $branch   = current_branch();
        $settings = PosSettings::current();

        $settingsData = $settings->toArray();
        if ($branch) {
            $settingsData['business_name']           = $branch->name;
            $settingsData['business_address']        = $branch->address;
            $settingsData['business_contact_number'] = $branch->phone;
            $settingsData['business_email']          = $branch->email;
        }

        return Inertia::render('Printer/Index', [
            'settings'     => $settingsData,
            'isBranchAdmin'=> \App\Services\RoleService::canEditSettings(),
        ]);
    }

    /**
     * Save branch-wide default printer configuration (Admin only).
     */
    public function saveBranchDefault(Request $request)
    {
        if (!\App\Services\RoleService::canEditSettings()) {
            abort(403, 'Only branch administrators can update branch-wide defaults.');
        }

        $data = $request->validate([
            'receipt_paper_size'   => 'required|in:80mm,a4,80MM,A4',
            'receipt_printer_name' => 'nullable|string|max:100',
            'printer_connection'   => 'required|in:kiosk_direct,network_ip,local_agent',
            'printer_ip_address'   => 'nullable|string|max:100',
            'receipt_copies'       => 'nullable|integer|min:1|max:10',
        ]);

        $settings = PosSettings::current();
        $settings->update([
            'receipt_paper_size'   => strtoupper($data['receipt_paper_size']) === 'A4' ? 'a4' : '80mm',
            'receipt_printer_name' => $data['receipt_printer_name'] ?? 'Default POS Printer',
            'printer_connection'   => $data['printer_connection'],
            'printer_ip_address'   => $data['printer_ip_address'],
            'receipt_copies'       => max(1, (int) ($data['receipt_copies'] ?? 1)),
        ]);

        return back()->with('success', 'Branch default printer configuration updated.');
    }
}
