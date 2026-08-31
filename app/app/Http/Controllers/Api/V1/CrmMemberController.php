<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCrmMemberRequest;
use App\Http\Requests\Crm\UpdateCrmMemberRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CrmMemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->canViewDirectory($request);
        $farmId = $request->integer('farm_id');
        $this->adminFarm($request, $farmId);
        $members = User::whereHas('farms', fn ($query) => $query->where('farms.id', $farmId))
            ->latest()->get();

        return response()->json(['data' => UserResource::collection($members)]);
    }

    public function store(StoreCrmMemberRequest $request): JsonResponse
    {
        $this->admin($request);
        $this->adminFarm($request, $request->integer('farm_id'));
        $member = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => Hash::make($request->string('password')),
            'role' => 'farmWorker',
            'crm_role' => $request->string('crm_role'),
        ]);
        $member->farms()->attach($request->integer('farm_id'));

        return response()->json(['data' => new UserResource($member)], 201);
    }

    public function update(UpdateCrmMemberRequest $request, User $user): JsonResponse
    {
        $this->admin($request);
        $this->sameFarm($request, $user);
        if ($user->crm_role === 'admin') abort(422, 'The admin account cannot be closed or reassigned.');
        $data = $request->validated();
        if (array_key_exists('crm_role', $data)) $user->crm_role = $data['crm_role'];
        if (array_key_exists('closed', $data)) $user->crm_closed_at = $data['closed'] ? now() : null;
        $user->save();

        return response()->json(['data' => new UserResource($user)]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->admin($request);
        $this->sameFarm($request, $user);
        if ($user->crm_role === 'admin') abort(422, 'The admin account cannot be deleted.');
        $user->delete();
        return response()->json(null, 204);
    }

    private function admin(Request $request): void
    {
        if ($request->user()->crm_role !== 'admin' && $request->user()->role !== 'farmOwner') {
            abort(403, 'Only CRM admins can manage CRM accounts.');
        }
    }

    private function canViewDirectory(Request $request): void
    {
        $role = $request->user()->crm_role ?? ($request->user()->role === 'farmOwner' ? 'admin' : null);
        if (! in_array($role, ['admin', 'customer_support'], true)) {
            abort(403, 'Only CRM staff can view CRM accounts.');
        }
    }

    private function adminFarm(Request $request, int $farmId): void
    {
        if (! $request->user()->farms()->where('farms.id', $farmId)->exists()) {
            abort(403, 'You do not have access to this farm.');
        }
    }

    private function sameFarm(Request $request, User $target): void
    {
        if ($target->crm_role === null) {
            abort(404, 'CRM account not found.');
        }

        $adminFarmIds = $request->user()->farms()->pluck('farms.id');
        if (! $target->farms()->whereIn('farms.id', $adminFarmIds)->exists()) {
            abort(404, 'CRM account not found.');
        }
    }
}
