<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->crmStaff($user);
        $farmId = $request->integer('farm_id');
        if (! $user->farms()->where('farms.id', $farmId)->exists()) abort(403, 'You do not have access to this farm.');
        $items = \DB::table('crm_notifications')->where('farm_id', $farmId)
            ->where(fn ($query) => $query->whereNull('recipient_id')->orWhere('recipient_id', $user->id))
            ->latest()->limit(50)->get();
        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->crm_role ?? ($user->role === 'farmOwner' ? 'admin' : null);
        if (! in_array($role, ['admin', 'customer_support'], true)) {
            abort(403, 'Only admins and customer support can send notifications.');
        }
        $data = $request->validate([
            'farm_id' => ['required', 'integer', 'exists:farms,id'],
            'message' => ['required', 'string', 'max:1000'],
            'recipient_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        if (! $user->farms()->where('farms.id', $data['farm_id'])->exists()) {
            abort(403, 'You do not have access to this farm.');
        }
        if (!empty($data['recipient_id']) && !User::whereKey($data['recipient_id'])->whereHas('farms', fn ($query) => $query->where('farms.id', $data['farm_id']))->exists()) abort(422, 'Recipient is not a member of this farm.');
        $id = \DB::table('crm_notifications')->insertGetId([...$data, 'sender_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['data' => \DB::table('crm_notifications')->find($id)], 201);
    }

    private function crmStaff(User $user): void
    {
        $role = $user->crm_role ?? ($user->role === 'farmOwner' ? 'admin' : null);
        if (! in_array($role, ['admin', 'finance', 'customer_support'], true)) {
            abort(403, 'Only CRM staff can access notifications.');
        }
    }
}
