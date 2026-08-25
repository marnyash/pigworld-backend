<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCustomerRequest;
use App\Http\Requests\Crm\UpdateCustomerRequest;
use App\Http\Resources\Crm\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $farmIds = $request->user()->farms()->pluck('farms.id');

        $customers = Customer::query()
            ->whereIn('farm_id', $farmIds)
            ->when($request->filled('farm_id'), fn ($query) => $query->where('farm_id', $request->integer('farm_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->toString();
                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('company', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->get();

        return response()->json(['data' => CustomerResource::collection($customers)]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorizeCrmAction($request, 'write');
        $this->authorizeFarm($request, $request->integer('farm_id'));

        $customer = Customer::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => new CustomerResource($customer)], 201);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeFarm($request, $customer->farm_id);

        return response()->json(['data' => new CustomerResource($customer)]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorizeCrmAction($request, 'write');
        $this->authorizeFarm($request, $customer->farm_id);

        $customer->update($request->validated());

        return response()->json(['data' => new CustomerResource($customer)]);
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeCrmAction($request, 'delete');
        $this->authorizeFarm($request, $customer->farm_id);

        $customer->delete();

        return response()->json(null, 204);
    }

    private function authorizeFarm(Request $request, int $farmId): void
    {
        $belongsToFarm = $request->user()->farms()->where('farms.id', $farmId)->exists();

        if (! $belongsToFarm) {
            throw ValidationException::withMessages(['farm_id' => ['You do not have access to this farm.']]);
        }
    }

    private function authorizeCrmAction(Request $request, string $action): void
    {
        $role = $request->user()->crm_role ?? ($request->user()->role === 'farmOwner' ? 'admin' : null);
        if ($action === 'delete' && $role !== 'admin') abort(403, 'Only CRM admins can delete customers.');
        if ($action === 'write' && !in_array($role, ['admin', 'finance'], true)) abort(403, 'This CRM role is read-only for customer records.');
    }
}
