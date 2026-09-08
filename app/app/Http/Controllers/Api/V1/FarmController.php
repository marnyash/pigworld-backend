<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreFarmRequest;
use App\Http\Resources\FarmResource;
use Illuminate\Http\JsonResponse;

class FarmController extends Controller
{
    public function store(StoreFarmRequest $request): JsonResponse
    {
        $farm = $request->user()->farms()->create($request->validated());

        return response()->json(['data' => new FarmResource($farm)], 201);
    }
}