<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    // api-contract.md §3.3: the five categories, ordered by name.
    public function index()
    {
        return response()->json(Category::orderBy('name')->get(['id', 'name']));
    }
}
