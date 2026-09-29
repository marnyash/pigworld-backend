<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreFarmRequest;
use App\Http\Resources\FarmResource;
use App\Models\Farm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FarmController extends Controller
{
    public function store(StoreFarmRequest $request): JsonResponse
    {
        $farm = $request->user()->farms()->create($request->validated());

        return response()->json(['data' => new FarmResource($farm)], 201);
    }

    public function update(Request $request, Farm $farm): JsonResponse
    {
        abort_unless($request->user()->farms()->where('farms.id', $farm->getKey())->exists(), 403);
        abort_if($request->exists('name'), 422, 'Farm name changes must be submitted for CRM approval.');
        abort_unless(
            $request->user()->role === 'farmOwner' ||
                ($request->user()->crm_role ?? null) === 'admin',
            403,
            'Only farm owners and CRM administrators can update farm settings.',
        );

        $farm->update($request->validate([
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ]));

        return response()->json(['data' => new FarmResource($farm->fresh())]);
    }
}
