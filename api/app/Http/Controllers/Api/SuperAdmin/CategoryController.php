<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Category::class);

        return CategoryResource::collection(Category::all());
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Category::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'regex:/\S/'],
        ]);

        Category::create(['name' => trim($data['name'])]);
    }

    public function destroy(Category $category)
    {
        Gate::authorize('delete', $category);

        $category->delete();

        return response()->noContent();
    }
}
