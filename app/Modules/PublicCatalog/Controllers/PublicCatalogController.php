<?php

namespace App\Modules\PublicCatalog\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Modules\PublicCatalog\Services\PublicCatalogQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicCatalogController extends Controller
{
    public function __construct(
        protected PublicCatalogQueryService $queryService
    ) {}

    /**
     * Display the public, read-only branch price list.
     */
    public function show(Request $request, string $slug)
    {
        // 1. Resolve branch live on every request (never cached)
        $branch = $this->queryService->resolveBranch($slug);

        // §4.2: If toggle off, branch disabled, or slug unknown -> return the identical generic "unavailable" view
        if (!$branch) {
            $companyName = (string) SystemSetting::get('company_name', config('app.name', 'Skynet POS'));
            $theme       = $this->queryService->getThemeConfig();
            return response()
                ->view('public-catalog.unavailable', [
                    'companyName' => $companyName,
                    'theme'       => $theme,
                ], 404)
                ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        // 2. Fetch cached, sanitized catalog data
        $filters = [
            'search'   => $request->query('search', ''),
            'category' => $request->query('category', ''),
            'sort'     => $request->query('sort', 'name_asc'),
            'page'     => $request->query('page', 1),
            'per_page' => $request->query('per_page', config('public_catalog.default_per_page', 24)),
        ];

        $catalog = $this->queryService->getCatalogData($branch, $filters);

        // 3. Render standalone, lightweight server-rendered Blade template
        return response()
            ->view('public-catalog.show', $catalog)
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=30');
    }
}
