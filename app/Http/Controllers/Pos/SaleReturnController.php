<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosSettings;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SaleReturnController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'sale_id'           => 'nullable|integer|exists:sales,id',
            'items'             => 'required|array|min:1',
            'items.*.item_id'   => 'required|integer|exists:items,id',
            'items.*.qty'                => 'required|integer|min:1',
            'items.*.unit_used'          => 'nullable|string',
            'items.*.imei_or_device_id'  => 'nullable|string|max:100',
            'items.*.reason'             => 'nullable|string|max:255',
        ]);

        try {
            \App\Services\ReturnService::process(
                $data['sale_id'] ?? null,
                $data['items'],
                Auth::user()
            );
            \App\Services\ActivityLogger::return(
                "Processed sale return for " . count($data['items']) . " item(s)",
                current_branch()?->id,
                [
                    'sale_id'    => $data['sale_id'] ?? null,
                    'item_count' => count($data['items']),
                ]
            );

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => 'Return processed and inventory restocked.']);
            }
            return back()->with('success', 'Return processed and inventory restocked.');
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['errors' => ['return' => $e->getMessage()]], 422);
            }
            return back()->withErrors(['return' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['errors' => ['return' => 'Return processing failed.']], 500);
            }
            return back()->withErrors(['return' => 'Return processing failed.']);
        }
    }
}
