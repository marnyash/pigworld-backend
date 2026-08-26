<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmReportController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $this->authorizeReportAccess($request);
        $farmIds = $request->user()->farms()->pluck('farms.id');
        $farmId = $request->filled('farm_id') ? $request->integer('farm_id') : null;

        if ($farmId !== null && ! $farmIds->contains($farmId)) {
            abort(403, 'You do not have access to this farm.');
        }

        $customers = Customer::query()
            ->whereIn('farm_id', $farmIds)
            ->when($farmId !== null, fn ($query) => $query->where('farm_id', $farmId));
        $total = (clone $customers)->count();
        $statusCounts = (clone $customers)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'data' => [
                'total_customers' => $total,
                'active_relationships' => $total - (int) ($statusCounts['lost'] ?? 0),
                'qualified' => (int) ($statusCounts['qualified'] ?? 0),
                'won' => (int) ($statusCounts['won'] ?? 0),
                'conversion_rate' => $total > 0 ? round(((int) ($statusCounts['won'] ?? 0) / $total) * 100, 2) : 0,
                'by_status' => $statusCounts,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function authorizeReportAccess(Request $request): void
    {
        $role = $request->user()->crm_role ?? ($request->user()->role === 'farmOwner' ? 'admin' : null);

        if (! in_array($role, ['admin', 'finance'], true)) {
            abort(403, 'Only CRM admins and Finance can access reports.');
        }
    }
}
