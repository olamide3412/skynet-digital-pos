<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ImportItemType;
use App\Models\ImportLibraryFile;
use App\Services\ActivityLogger;
use App\Services\ItemImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ImportLibraryController extends Controller
{
    protected ItemImportService $importService;

    public function __construct(ItemImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Display the Super Admin Import File Library.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $typeId = $request->query('type_id');
        $status = $request->query('status', 'active'); // 'active', 'archived', 'all'

        $query = ImportLibraryFile::with(['itemType', 'user:id,name,email'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('item_type_tag', 'like', "%{$search}%")
                        ->orWhere('file_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($typeId, function ($q) use ($typeId) {
                $q->where('item_type_id', $typeId);
            })
            ->when($status !== 'all', function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->latest();

        $files = $query->paginate(12)->withQueryString();

        $itemTypes = ImportItemType::where('is_active', true)
            ->withCount(['libraryFiles as files_count' => fn($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->get();

        $branches = Branch::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_active']);

        $stats = [
            'total_files'            => ImportLibraryFile::count(),
            'active_files'           => ImportLibraryFile::where('status', 'active')->count(),
            'archived_files'         => ImportLibraryFile::where('status', 'archived')->count(),
            'total_items_in_library' => (int) ImportLibraryFile::where('status', 'active')->sum('row_count'),
            'total_store_types'      => ImportItemType::where('is_active', true)->count(),
            'total_imports_done'     => (int) ImportLibraryFile::sum('import_count'),
        ];

        return Inertia::render('SuperAdmin/ImportLibrary/Index', [
            'files'      => $files,
            'item_types' => $itemTypes,
            'branches'   => $branches,
            'stats'      => $stats,
            'filters'    => [
                'search'  => $search,
                'type_id' => $typeId ? (int) $typeId : null,
                'status'  => $status,
            ],
        ]);
    }

    /**
     * Upload and store a new item template file into the library.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'item_type_id'     => 'nullable|exists:import_item_types,id',
            'custom_type_name' => 'nullable|string|max:100',
            'file'             => 'required|file|mimes:xlsx,xls,csv,txt|max:20480', // max 20MB
            'description'      => 'nullable|string|max:1000',
        ]);

        $uploadedFile = $request->file('file');
        $itemTypeId = $data['item_type_id'] ?? null;
        $itemTypeTag = null;

        // If custom type tag was typed inline
        if (empty($itemTypeId) && !empty($data['custom_type_name'])) {
            $typeName = trim($data['custom_type_name']);
            $type = ImportItemType::firstOrCreate(
                ['slug' => Str::slug($typeName)],
                ['name' => $typeName, 'is_active' => true]
            );
            $itemTypeId = $type->id;
            $itemTypeTag = $type->name;
        } elseif ($itemTypeId) {
            $type = ImportItemType::find($itemTypeId);
            $itemTypeTag = $type?->name;
        }

        try {
            $parsed = $this->importService->parseFile($uploadedFile);
        } catch (Throwable $e) {
            return back()->with('error', 'Error reading spreadsheet: ' . $e->getMessage());
        }

        if ($parsed['valid_rows'] === 0) {
            return back()->with('error', 'The uploaded file does not contain any valid item rows. Please download the template to ensure correct column headers.');
        }

        // Store file securely
        $ext = strtolower($uploadedFile->getClientOriginalExtension());
        $storedPath = $uploadedFile->store('import-library/files', 'local');

        $libraryFile = ImportLibraryFile::create([
            'name'          => $data['name'],
            'item_type_id'  => $itemTypeId,
            'item_type_tag' => $itemTypeTag ?: 'General Retail',
            'file_path'     => $storedPath,
            'file_name'     => $uploadedFile->getClientOriginalName(),
            'file_format'   => $ext ?: 'xlsx',
            'file_size'     => $uploadedFile->getSize(),
            'row_count'     => $parsed['valid_rows'],
            'user_id'       => auth()->id(),
            'status'        => 'active',
            'description'   => $data['description'] ?? null,
            'import_count'  => 0,
        ]);

        ActivityLogger::log(
            actionType: 'library',
            message: "Super Admin uploaded '{$libraryFile->name}' ({$libraryFile->row_count} items) to the Import Library.",
            details: [
                'file_id'    => $libraryFile->id,
                'name'       => $libraryFile->name,
                'item_type'  => $libraryFile->item_type_tag,
                'row_count'  => $libraryFile->row_count,
            ]
        );

        return back()->with('success', "File '{$libraryFile->name}' successfully added to Import Library ({$libraryFile->row_count} items).");
    }

    /**
     * Replace the underlying spreadsheet file for an existing library entry.
     * Retains name, category type, and past import history.
     */
    public function replace(Request $request, ImportLibraryFile $libraryFile): RedirectResponse
    {
        $data = $request->validate([
            'name'         => 'nullable|string|max:255',
            'item_type_id' => 'nullable|exists:import_item_types,id',
            'file'         => 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
            'description'  => 'nullable|string|max:1000',
        ]);

        $uploadedFile = $request->file('file');

        try {
            $parsed = $this->importService->parseFile($uploadedFile);
        } catch (Throwable $e) {
            return back()->with('error', 'Error reading new spreadsheet: ' . $e->getMessage());
        }

        if ($parsed['valid_rows'] === 0) {
            return back()->with('error', 'The replacement file does not contain any valid item rows.');
        }

        // Delete old file if present
        if ($libraryFile->file_path && Storage::disk('local')->exists($libraryFile->file_path)) {
            Storage::disk('local')->delete($libraryFile->file_path);
        }

        $ext = strtolower($uploadedFile->getClientOriginalExtension());
        $storedPath = $uploadedFile->store('import-library/files', 'local');

        $updates = [
            'file_path'   => $storedPath,
            'file_name'   => $uploadedFile->getClientOriginalName(),
            'file_format' => $ext ?: 'xlsx',
            'file_size'   => $uploadedFile->getSize(),
            'row_count'   => $parsed['valid_rows'],
            'user_id'     => auth()->id(),
        ];

        if (!empty($data['name'])) {
            $updates['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updates['description'] = $data['description'];
        }
        if (!empty($data['item_type_id'])) {
            $type = ImportItemType::find($data['item_type_id']);
            $updates['item_type_id']  = $type?->id;
            $updates['item_type_tag'] = $type?->name;
        }

        $libraryFile->update($updates);

        ActivityLogger::log(
            actionType: 'library',
            message: "Super Admin updated/replaced file version for '{$libraryFile->name}' ({$libraryFile->row_count} items).",
            details: [
                'file_id'   => $libraryFile->id,
                'name'      => $libraryFile->name,
                'row_count' => $libraryFile->row_count,
            ]
        );

        return back()->with('success', "'{$libraryFile->name}' successfully updated with new file version ({$libraryFile->row_count} items).");
    }

    /**
     * Toggle file status between active and archived.
     */
    public function toggleStatus(ImportLibraryFile $libraryFile): RedirectResponse
    {
        $newStatus = $libraryFile->status === 'active' ? 'archived' : 'active';
        $libraryFile->update(['status' => $newStatus]);

        $actionText = $newStatus === 'archived' ? 'archived' : 'restored to active';
        return back()->with('success', "'{$libraryFile->name}' has been {$actionText}.");
    }

    /**
     * Permanently delete a file entry from the library.
     * Note: Does NOT delete items already imported into branches.
     */
    public function destroy(ImportLibraryFile $libraryFile): RedirectResponse
    {
        $fileName = $libraryFile->name;

        // Delete physical file
        if ($libraryFile->file_path && Storage::disk('local')->exists($libraryFile->file_path)) {
            Storage::disk('local')->delete($libraryFile->file_path);
        }

        $libraryFile->delete();

        ActivityLogger::log(
            actionType: 'library',
            message: "Super Admin permanently deleted library file entry '{$fileName}'.",
            details: ['deleted_file_name' => $fileName]
        );

        return back()->with('success', "'{$fileName}' was permanently removed from the Import Library.");
    }

    /**
     * Download the stored file.
     */
    public function download(ImportLibraryFile $libraryFile): StreamedResponse|RedirectResponse
    {
        if (!$libraryFile->file_path || !Storage::disk('local')->exists($libraryFile->file_path)) {
            return back()->with('error', 'File not found in storage.');
        }

        return Storage::disk('local')->download($libraryFile->file_path, $libraryFile->file_name);
    }

    /**
     * Download blank or pre-filled sample template (XLSX or CSV).
     */
    public function downloadTemplate(Request $request, string $format): StreamedResponse
    {
        $format = in_array(strtolower($format), ['csv', 'xlsx']) ? strtolower($format) : 'xlsx';
        $withSampleData = $request->boolean('sample', false);

        return $this->importService->generateTemplate($format, $withSampleData);
    }

    /**
     * Parse and preview items from a library file or uploaded file against a target branch.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id'       => 'required|exists:branches,id',
            'library_file_id' => 'nullable|exists:import_library_files,id',
            'file'            => 'nullable|file|mimes:xlsx,xls,csv,txt|max:20480',
        ]);

        $branch = Branch::findOrFail($request->branch_id);

        try {
            if ($request->hasFile('file')) {
                $parsed = $this->importService->parseFile($request->file('file'));
            } elseif ($request->library_file_id) {
                $libraryFile = ImportLibraryFile::findOrFail($request->library_file_id);
                if (!Storage::disk('local')->exists($libraryFile->file_path)) {
                    return response()->json(['success' => false, 'message' => 'Library file not found on disk.'], 404);
                }
                $parsed = $this->importService->parseFile(Storage::disk('local')->path($libraryFile->file_path));
            } else {
                return response()->json(['success' => false, 'message' => 'No file or library file selected.'], 422);
            }

            $preview = $this->importService->previewImport($branch, $parsed['rows']);

            return response()->json([
                'success'                 => true,
                'total_rows'              => $preview['total_rows'],
                'valid_rows'              => $preview['valid_rows'],
                'invalid_rows'            => $preview['invalid_rows'],
                'barcode_conflicts_count' => $preview['barcode_conflicts_count'],
                'existing_names_count'    => $preview['existing_names_count'],
                'sample_rows'             => $preview['sample_rows'],
                'all_rows'                => $preview['rows'],
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Commit and execute an import into a branch.
     */
    public function importToBranch(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'branch_id'              => 'required|exists:branches,id',
            'source_type'            => 'required|in:library,upload',
            'library_file_id'        => 'nullable|required_if:source_type,library|exists:import_library_files,id',
            'file'                   => 'nullable|required_if:source_type,upload|file|mimes:xlsx,xls,csv,txt|max:20480',
            'save_to_library'        => 'nullable|boolean',
            'library_name'           => 'nullable|required_if:save_to_library,true|string|max:255',
            'item_type_id'           => 'nullable|exists:import_item_types,id',
            'custom_type_name'       => 'nullable|string|max:100',
            'on_duplicate_barcode'   => 'nullable|in:skip,leave_empty,generate_new',
            'default_stock_location' => 'nullable|in:front_store,back_store',
        ]);

        $branch = Branch::findOrFail($data['branch_id']);
        $libraryFile = null;

        try {
            if ($data['source_type'] === 'library') {
                $libraryFile = ImportLibraryFile::findOrFail($data['library_file_id']);
                if (!Storage::disk('local')->exists($libraryFile->file_path)) {
                    return back()->with('error', 'Selected library file does not exist on storage.');
                }
                $parsed = $this->importService->parseFile(Storage::disk('local')->path($libraryFile->file_path));
            } else {
                $uploadedFile = $request->file('file');
                $parsed = $this->importService->parseFile($uploadedFile);

                // If user checked "Also save to Import Library"
                if (!empty($data['save_to_library'])) {
                    $itemTypeId = $data['item_type_id'] ?? null;
                    $itemTypeTag = null;

                    if (empty($itemTypeId) && !empty($data['custom_type_name'])) {
                        $typeName = trim($data['custom_type_name']);
                        $type = ImportItemType::firstOrCreate(
                            ['slug' => Str::slug($typeName)],
                            ['name' => $typeName, 'is_active' => true]
                        );
                        $itemTypeId = $type->id;
                        $itemTypeTag = $type->name;
                    } elseif ($itemTypeId) {
                        $type = ImportItemType::find($itemTypeId);
                        $itemTypeTag = $type?->name;
                    }

                    $ext = strtolower($uploadedFile->getClientOriginalExtension());
                    $storedPath = $uploadedFile->store('import-library/files', 'local');

                    $libraryFile = ImportLibraryFile::create([
                        'name'          => $data['library_name'] ?: 'Imported Catalog ' . date('Y-m-d'),
                        'item_type_id'  => $itemTypeId,
                        'item_type_tag' => $itemTypeTag ?: 'General Retail',
                        'file_path'     => $storedPath,
                        'file_name'     => $uploadedFile->getClientOriginalName(),
                        'file_format'   => $ext ?: 'xlsx',
                        'file_size'     => $uploadedFile->getSize(),
                        'row_count'     => $parsed['valid_rows'],
                        'user_id'       => auth()->id(),
                        'status'        => 'active',
                        'import_count'  => 0,
                    ]);
                }
            }

            $options = [
                'on_duplicate_barcode'   => $data['on_duplicate_barcode'] ?? 'skip',
                'default_stock_location' => $data['default_stock_location'] ?? 'front_store',
            ];

            $result = $this->importService->executeImport(
                branch: $branch,
                rows: $parsed['rows'],
                options: $options,
                sourceFile: $libraryFile,
                userId: auth()->id()
            );

            $msg = "Import complete! Successfully imported {$result['imported_count']} items into {$branch->name}";
            if ($result['skipped_count'] > 0) {
                $msg .= " ({$result['skipped_count']} rows skipped).";
            } else {
                $msg .= ".";
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'result'  => $result,
                ]);
            }

            return back()->with('success', $msg);
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
