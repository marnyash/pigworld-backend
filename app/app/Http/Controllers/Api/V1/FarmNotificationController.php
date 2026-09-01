<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FarmNotificationResource;
use App\Models\Farm;
use App\Models\FarmNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FarmNotificationController extends Controller
{
    public function index(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeMember($request, $farm);

        $notifications = FarmNotification::query()
            ->where('farm_id', $farm->id)
            ->where(function ($query) use ($request) {
                $query->whereNull('recipient_id')->orWhere('recipient_id', $request->user()->id);
            })
            ->latest()
            ->paginate(30);

        return FarmNotificationResource::collection($notifications)->response();
    }

    public function markAsRead(Request $request, Farm $farm, FarmNotification $notification): JsonResponse
    {
        $this->authorizeMember($request, $farm);
        $this->authorizeNotification($request, $farm, $notification);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json(['data' => new FarmNotificationResource($notification)]);
    }

    private function authorizeMember(Request $request, Farm $farm): void
    {
        $belongs = $request->user()->farms()->where('farms.id', $farm->id)->exists();

        if (! $belongs) {
            abort(403, 'You do not have access to this farm.');
        }
    }

    private function authorizeNotification(Request $request, Farm $farm, FarmNotification $notification): void
    {
        $isAccessible = $notification->farm_id === $farm->id
            && ($notification->recipient_id === null || $notification->recipient_id === $request->user()->id);

        if (! $isAccessible) {
            abort(404, 'Notification not found.');
        }
    }
}