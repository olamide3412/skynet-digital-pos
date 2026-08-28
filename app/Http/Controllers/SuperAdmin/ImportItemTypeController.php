<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ImportItemType;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ImportItemTypeController extends Controller
{
    /**
     * Create a new item type tag.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:import_item_types,name',
            'description' => 'nullable|string|max:500',
        ]);

        $type = ImportItemType::create([
            'name'        => $data['name'],
            'slug'        => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_active'   => true,
        ]);

        ActivityLogger::log(
            actionType: 'library',
            message: "Super Admin created item type tag '{$type->name}'.",
            details: ['type_id' => $type->id, 'name' => $type->name]
        );

        return back()->with('success', "Category tag '{$type->name}' created.");
    }

    /**
     * Update an item type tag.
     */
    public function update(Request $request, ImportItemType $itemType): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:import_item_types,name,' . $itemType->id,
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
        ]);

        $oldName = $itemType->name;
        $itemType->update([
            'name'        => $data['name'],
            'slug'        => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_active'   => $request->has('is_active') ? (bool) $request->is_active : $itemType->is_active,
        ]);

        // Update denormalized tag name on library files
        if ($oldName !== $data['name']) {
            $itemType->libraryFiles()->update(['item_type_tag' => $data['name']]);
        }

        ActivityLogger::log(
            actionType: 'library',
            message: "Super Admin updated item type tag '{$itemType->name}'.",
            details: ['type_id' => $itemType->id, 'name' => $itemType->name]
        );

        return back()->with('success', "Category tag '{$itemType->name}' updated.");
    }

    /**
     * Delete an item type tag.
     * Note: Gracefully clears foreign keys on library files.
     */
    public function destroy(ImportItemType $itemType): RedirectResponse
    {
        $name = $itemType->name;
        $itemType->delete();

        ActivityLogger::log(
            actionType: 'library',
            message: "Super Admin deleted item type tag '{$name}'.",
            details: ['deleted_type' => $name]
        );

        return back()->with('success', "Category tag '{$name}' deleted.");
    }
}
