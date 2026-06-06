<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function index(){
       return CategoryResource::collection(Category::all());
    }

    public function store(Request $request)
    {
        Category::create($request->all());
    }

    public function destroy(Category $category)
    {
        Gate::authorize('delete', $category);

        $category->delete();
        return response()->noContent();
    }
}
