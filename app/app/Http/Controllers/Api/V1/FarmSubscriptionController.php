<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\UpdateSubscriptionRequest;
use App\Http\Resources\FarmResource;
use App\Models\Farm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FarmSubscriptionController extends Controller
{
    public function update(UpdateSubscriptionRequest $request, Farm $farm): JsonResponse
    {
        $user = $request->user();
        $isOwner = $user->role === 'farmOwner'
            && $user->farms()->where('farms.id', $farm->id)->exists();

        if (! $isOwner) {
            abort(403, 'Only the farm owner can choose a subscription.');
        }

        $farm->update(['subscription_plan' => $request->string('plan')->toString()]);

        return response()->json(['farm' => new FarmResource($farm->fresh())]);
    }
}
