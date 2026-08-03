<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttributeRequest;
use App\Http\Resources\AttributeResource;
use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AttributeController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Attribute::class);

        $attributes = Attribute::query()
            ->when($request->boolean('variant_only'), fn ($query) => $query->variantDefining())
            ->when($request->boolean('filterable_only'), fn ($query) => $query->filterable())
            ->withCount('values')
            ->with('values')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return AttributeResource::collection($attributes);
    }

    public function show(Attribute $attribute)
    {
        Gate::authorize('view', $attribute);

        return response(new AttributeResource($attribute->load('values')));
    }

    public function store(AttributeRequest $request)
    {
        Gate::authorize('create', Attribute::class);

        $attribute = Attribute::create($request->validated());

        return new AttributeResource($attribute->load('values'));
    }

    public function update(Attribute $attribute, AttributeRequest $request)
    {
        Gate::authorize('update', $attribute);

        $attribute->update($request->validated());

        return new AttributeResource($attribute->refresh()->load('values'));
    }

    public function destroy(Attribute $attribute)
    {
        Gate::authorize('delete', $attribute);

        $attribute->delete();

        return response()->noContent();
    }
}
