<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmDirectoryController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $this->authorizeCrmAccess($request);
        $farmIds = $request->user()->farms()->pluck('farms.id');
        $farms = $request->user()->farms()
            ->with(['users' => fn ($query) => $query->select('users.id', 'users.name', 'users.email', 'users.phone', 'users.role', 'users.crm_closed_at')])
            ->orderBy('name')
            ->get();

        $customers = Customer::query()
            ->whereIn('farm_id', $farmIds)
            ->with('farm:id,name')
            ->withCount(['tasks as open_tasks_count' => fn ($query) => $query->where('status', 'open')])
            ->latest()
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => (string) $customer->id,
                'farm_id' => (string) $customer->farm_id,
                'farm_name' => $customer->farm?->name,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'company' => $customer->company,
                'type' => $customer->type,
                'status' => $customer->status,
                'open_tasks_count' => (int) $customer->open_tasks_count,
            ]);
        $members = $farms->flatMap(fn ($farm) => $farm->users->map(fn ($member) => [
            'id' => (string) $member->id,
            'farm_id' => (string) $farm->id,
            'farm_name' => $farm->name,
            'name' => $member->name,
            'email' => $member->email,
            'phone' => $member->phone,
            'role' => $member->role,
            'status' => $member->crm_closed_at ? 'suspended' : 'active',
        ]))->values();

        $relationships = $farms->map(function ($farm): array {
            $users = $farm->users;
            return [
                'farm_id' => (string) $farm->id,
                'farm_name' => $farm->name,
                'owner' => $this->memberSummary($users->firstWhere('role', 'farmOwner'), $farm->id, $farm->name),
                'managers' => $users->where('role', 'farmManager')->values()->map(fn ($member) => $this->memberSummary($member, $farm->id, $farm->name))->values(),
                'workers' => $users->where('role', 'farmWorker')->values()->map(fn ($member) => $this->memberSummary($member, $farm->id, $farm->name))->values(),
            ];
        })->values();

        return response()->json(['data' => [
            'customers' => $customers,
            'farm_owners' => $members->where('role', 'farmOwner')->values(),
            'farm_managers' => $members->where('role', 'farmManager')->values(),
            'farm_workers' => $members->where('role', 'farmWorker')->values(),
            'relationships' => $relationships,
        ]]);
    }

    private function memberSummary($member, int $farmId, string $farmName): ?array
    {
        if ($member === null) return null;
        return [
            'id' => (string) $member->id,
            'farm_id' => (string) $farmId,
            'farm_name' => $farmName,
            'name' => $member->name,
            'email' => $member->email,
            'phone' => $member->phone,
            'role' => $member->role,
        ];
    }

    private function authorizeCrmAccess(Request $request): void
    {
        $role = $request->user()->crm_role ?? ($request->user()->role === 'farmOwner' ? 'admin' : null);
        if (! in_array($role, ['admin', 'finance', 'customer_support'], true)) {
            abort(403, 'Only CRM staff can access directories.');
        }
    }
}
