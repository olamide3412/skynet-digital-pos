<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AiCreditPack;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AiCreditPackController extends Controller
{
    public function index()
    {
        $packs = AiCreditPack::withCount('topups')
            ->orderBy('sort_order')
            ->orderBy('credits')
            ->get();

        return Inertia::render('SuperAdmin/CreditPacks/Index', [
            'packs' => $packs,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'credits'    => 'required|integer|min:1',
            'price'      => 'required|numeric|min:0',
            'currency'   => 'nullable|string|max:10',
            'is_active'  => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $pack = AiCreditPack::create([
            'name'       => $validated['name'],
            'credits'    => $validated['credits'],
            'price'      => $validated['price'],
            'currency'   => $validated['currency'] ?? 'NGN',
            'is_active'  => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        ActivityLogger::setting("Created new AI credit pack: {$pack->name} ({$pack->credits} credits)");

        return back()->with('success', 'Credit pack created successfully.');
    }

    public function update(Request $request, AiCreditPack $creditPack)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'credits'    => 'required|integer|min:1',
            'price'      => 'required|numeric|min:0',
            'currency'   => 'nullable|string|max:10',
            'is_active'  => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $creditPack->update($validated);

        ActivityLogger::setting("Updated AI credit pack: {$creditPack->name}");

        return back()->with('success', 'Credit pack updated successfully.');
    }

    public function toggle(AiCreditPack $creditPack)
    {
        $creditPack->update(['is_active' => !$creditPack->is_active]);

        $status = $creditPack->is_active ? 'activated' : 'deactivated';
        ActivityLogger::setting("{$status} AI credit pack: {$creditPack->name}");

        return back()->with('success', "Credit pack {$status} successfully.");
    }

    public function destroy(AiCreditPack $creditPack)
    {
        $name = $creditPack->name;
        $creditPack->delete();

        ActivityLogger::setting("Deleted AI credit pack: {$name}");

        return back()->with('success', 'Credit pack deleted successfully.');
    }
}
