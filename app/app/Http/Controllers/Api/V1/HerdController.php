<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Herd\StoreAnimalRequest;
use App\Http\Requests\Herd\UpdateAnimalRequest;
use App\Http\Resources\Herd\AnimalResource;
use App\Models\Animal;
use App\Models\Farm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HerdController extends Controller
{
    public function index(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeHerdAccess($request, $farm);

        return response()->json([
            'data' => AnimalResource::collection($farm->animals()->latest()->get()),
        ]);
    }

    public function store(StoreAnimalRequest $request, Farm $farm): JsonResponse
    {
        $this->authorizeHerdAccess($request, $farm);

        $animal = $farm->animals()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => new AnimalResource($animal)], 201);
    }

    public function show(Request $request, Farm $farm, Animal $animal): JsonResponse
    {
        $this->authorizeHerdAccess($request, $farm);
        abort_if($animal->farm_id !== $farm->id, 404);

        return response()->json(['data' => new AnimalResource($animal)]);
    }

    public function update(UpdateAnimalRequest $request, Farm $farm, Animal $animal): JsonResponse
    {
        $this->authorizeHerdAccess($request, $farm);
        abort_if($animal->farm_id !== $farm->id, 404);
        $animal->update($request->validated());

        return response()->json(['data' => new AnimalResource($animal->fresh())]);
    }

    public function destroy(Request $request, Farm $farm, Animal $animal): JsonResponse
    {
        $this->authorizeHerdAccess($request, $farm);
        abort_if($animal->farm_id !== $farm->id, 404);
        $animal->update(['status' => 'deceased']);

        return response()->json(['data' => new AnimalResource($animal->fresh())]);
    }

    private function authorizeHerdAccess(Request $request, Farm $farm): void
    {
        $user = $request->user();
        $membership = $user->farms()->where('farms.id', $farm->id)->first();

        if ($membership === null) {
            abort(403, 'You do not have access to this farm.');
        }

        if ($user->role === 'farmOwner') {
            return;
        }

        $permissions = $membership->pivot->permissions === null
            ? $this->defaultPermissions($user->role)
            : json_decode($membership->pivot->permissions, true);

        if (! in_array('manageHerd', $permissions ?? [], true)) {
            abort(403, 'You do not have permission to manage this herd.');
        }
    }

    private function defaultPermissions(string $role): array
    {
        return match ($role) {
            'farmManager' => ['viewDashboard', 'manageHerd', 'manageBreeding', 'manageFeed', 'viewReports'],
            'farmWorker' => ['viewDashboard', 'manageHerd', 'manageFeed'],
            default => [],
        };
    }
}
