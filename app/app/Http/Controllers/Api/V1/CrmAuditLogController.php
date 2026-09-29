<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CrmAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmAuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $farmId = $request->integer('farm_id');
        $this->authorizeFarm($request, $farmId);

        $logs = CrmAuditLog::query()
            ->where('farm_id', $farmId)
            ->with('user:id,name,email')
            ->latest()
            ->limit(min(max($request->integer('per_page', 50), 1), 100))
            ->get()
            ->map(fn (CrmAuditLog $log): array => $this->serialize($log));

        return response()->json(['data' => $logs]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);
        $data = $request->validate([
            'farm_id' => ['required', 'integer', 'exists:farms,id'],
            'action' => ['required', 'string', 'max:200'],
            'module' => ['required', 'string', 'max:80'],
            'metadata' => ['nullable', 'array'],
        ]);
        $this->authorizeFarm($request, (int) $data['farm_id']);

        $log = CrmAuditLog::create([
            ...$data,
            'user_id' => $request->user()->id,
        ])->load('user:id,name,email');

        return response()->json(['data' => $this->serialize($log)], 201);
    }

    private function serialize(CrmAuditLog $log): array
    {
        return [
            'id' => (string) $log->id,
            'staff' => $log->user?->name ?? $log->user?->email ?? 'Former CRM user',
            'action' => $log->action,
            'module' => $log->module,
            'metadata' => $log->metadata ?? [],
            'at' => $log->created_at?->toIso8601String(),
        ];
    }

    private function authorizeAdmin(Request $request): void
    {
        if (($request->user()->crm_role ?? null) !== 'admin' && $request->user()->role !== 'farmOwner') {
            abort(403, 'Only CRM admins can view staff audit logs.');
        }
    }

    private function authorizeStaff(Request $request): void
    {
        $role = $request->user()->crm_role ?? ($request->user()->role === 'farmOwner' ? 'admin' : null);
        if (! in_array($role, ['admin', 'finance', 'customer_support'], true)) {
            abort(403, 'Only CRM staff can create audit events.');
        }
    }

    private function authorizeFarm(Request $request, int $farmId): void
    {
        if (! $request->user()->farms()->whereKey($farmId)->exists()) {
            abort(403, 'You do not have access to this farm.');
        }
    }
}
