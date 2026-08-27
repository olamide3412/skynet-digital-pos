<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $defaultBranch = Branch::orderBy('id')->first();

        return Inertia::render('Home', [
            'default_branch' => $defaultBranch ? [
                'id'       => $defaultBranch->id,
                'name'     => $defaultBranch->name,
                'slug'     => $defaultBranch->slug,
                'logo_url' => $defaultBranch->logo_path ? asset('storage/' . $defaultBranch->logo_path) : null,
            ] : null,
        ]);
    }
}
