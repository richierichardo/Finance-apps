<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Return all categories (id, name, slug) for budget form dropdown.
     */
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('name')->get(['id', 'name', 'slug']);

        return response()->json($categories);
    }
}
