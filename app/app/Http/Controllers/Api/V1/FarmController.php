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
        $farm->update($request->validate(['name' => ['required', 'string', 'max:255']]));

        return response()->json(['data' => new FarmResource($farm->fresh())]);
    }
}
