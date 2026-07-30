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
            'sale_id' => 'required|integer|exists:sales,id',
            'items'   => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:items,id',
            'items.*.qty'     => 'required|integer|min:1',
            'items.*.reason'  => 'nullable|string|max:255',
        ]);

        try {
            ReturnService::process($data['sale_id'], $data['items'], Auth::user());
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
