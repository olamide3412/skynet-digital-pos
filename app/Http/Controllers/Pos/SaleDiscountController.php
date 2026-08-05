<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\SaleDiscount;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SaleDiscountController extends Controller
{
    public function index()
    {
        $discounts = SaleDiscount::latest()->get();

        return Inertia::render('Discounts/Index', [
            'discounts' => $discounts,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'discount_type'   => 'required|in:percentage,fixed',
            'discount_value'  => 'required|numeric|min:0',
            'start_date_time' => 'required|date',
            'end_date_time'   => 'required|date|after_or_equal:start_date_time',
            'is_apply'        => 'boolean',
        ]);

        SaleDiscount::create($data);

        return back()->with('success', 'Discount rule created successfully.');
    }

    public function update(Request $request, $branchParam, SaleDiscount $discount)
    {
        $data = $request->validate([
            'discount_type'   => 'required|in:percentage,fixed',
            'discount_value'  => 'required|numeric|min:0',
            'start_date_time' => 'required|date',
            'end_date_time'   => 'required|date|after_or_equal:start_date_time',
            'is_apply'        => 'boolean',
        ]);

        $discount->update($data);

        return back()->with('success', 'Discount rule updated successfully.');
    }

    public function destroy($branchParam, SaleDiscount $discount)
    {
        $discount->delete();

        return back()->with('success', 'Discount rule deleted.');
    }
}
