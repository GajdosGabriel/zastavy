<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttributeValueRequest;
use App\Http\Resources\AttributeValueResource;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Support\Facades\Gate;

class AttributeValueController extends Controller
{
    public function index(Attribute $attribute)
    {
        Gate::authorize('view', $attribute);

        return AttributeValueResource::collection($attribute->values);
    }

    public function store(Attribute $attribute, AttributeValueRequest $request)
    {
        Gate::authorize('update', $attribute);

        $value = $attribute->values()->create($request->validated());

        return new AttributeValueResource($value->refresh());
    }

    public function update(Attribute $attribute, AttributeValue $value, AttributeValueRequest $request)
    {
        Gate::authorize('update', $attribute);

        abort_unless($value->attribute_id === $attribute->id, 404);

        $value->update($request->validated());

        return new AttributeValueResource($value->refresh());
    }

    public function destroy(Attribute $attribute, AttributeValue $value)
    {
        Gate::authorize('update', $attribute);

        abort_unless($value->attribute_id === $attribute->id, 404);

        // Hodnota použitá na variante sa nemaže — variant by stratil identitu.
        if ($value->variants()->exists()) {
            return response()->json([
                'message' => 'Hodnotu nemožno zmazať, používa ju aspoň jeden variant.',
            ], 422);
        }

        $value->delete();

        return response()->noContent();
    }
}
