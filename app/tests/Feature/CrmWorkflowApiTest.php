<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CrmTask;
use App\Models\Farm;
use App\Models\CustomerOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_staff_can_assign_customer_create_tasks_and_read_timeline(): void
    {
        $admin = User::factory()->create(['role' => 'farmOwner']);
        $support = User::factory()->create(['role' => 'farmWorker', 'crm_role' => 'customer_support']);
        $farm = Farm::create(['name' => 'Workflow Farm']);
        $farm->users()->attach([$admin->id, $support->id]);

        $customer = Customer::create([
            'farm_id' => $farm->id,
            'created_by' => $admin->id,
            'name' => 'Workflow Customer',
            'status' => 'new',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/crm/customers/{$customer->id}", [
                'assigned_user_id' => $support->id,
                'status' => 'contacted',
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_user_id', (string) $support->id)
            ->assertJsonPath('data.status', 'contacted');

        $task = $this->actingAs($support, 'sanctum')
            ->postJson("/api/v1/crm/customers/{$customer->id}/tasks", [
                'assigned_to' => $support->id,
                'title' => 'Call customer',
                'priority' => 'high',
                'due_at' => '2026-09-12 10:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Call customer')
            ->json('data.id');

        $this->actingAs($support, 'sanctum')
            ->patchJson("/api/v1/crm/customers/{$customer->id}/tasks/{$task}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/crm/customers/{$customer->id}/timeline")
            ->assertOk()
            ->assertJsonFragment(['type' => 'status_changed'])
            ->assertJsonFragment(['type' => 'assignment_changed'])
            ->assertJsonFragment(['type' => 'task_created'])
            ->assertJsonFragment(['type' => 'task_updated']);
    }

    public function test_assignees_must_be_crm_staff_in_the_customer_farm(): void
    {
        $admin = User::factory()->create(['role' => 'farmOwner']);
        $outsider = User::factory()->create(['role' => 'farmWorker', 'crm_role' => 'finance']);
        $farm = Farm::create(['name' => 'Private Farm']);
        $farm->users()->attach($admin->id);
        $customer = Customer::create(['farm_id' => $farm->id, 'created_by' => $admin->id, 'name' => 'Private Customer']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/crm/customers/{$customer->id}/tasks", [
                'assigned_to' => $outsider->id,
                'title' => 'Invalid assignment',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');
    }

    public function test_crm_dashboard_returns_farm_scoped_operational_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'farmOwner']);
        $support = User::factory()->create(['role' => 'farmWorker', 'crm_role' => 'customer_support']);
        $otherFarm = Farm::create(['name' => 'Other Farm']);
        $farm = Farm::create(['name' => 'Dashboard Farm']);
        $farm->users()->attach([$admin->id, $support->id]);
        $otherFarm->users()->attach(User::factory()->create(['role' => 'farmOwner'])->id);

        $customer = Customer::create([
            'farm_id' => $farm->id,
            'created_by' => $admin->id,
            'name' => 'Dashboard Customer',
            'status' => 'qualified',
        ]);
        Customer::create([
            'farm_id' => $otherFarm->id,
            'created_by' => $admin->id,
            'name' => 'Other Customer',
            'status' => 'won',
        ]);
        CrmTask::create([
            'farm_id' => $farm->id,
            'customer_id' => $customer->id,
            'created_by' => $admin->id,
            'title' => 'Due today',
            'due_at' => now()->addHours(2),
            'assigned_to' => $support->id,
        ]);

        $this->actingAs($support, 'sanctum')
            ->getJson('/api/v1/crm/dashboard/overview?farm_id='.$farm->id)
            ->assertOk()
            ->assertJsonPath('data.customers.total', 1)
            ->assertJsonPath('data.customers.qualified', 1)
            ->assertJsonPath('data.customers.by_status.qualified', 1)
            ->assertJsonPath('data.tasks.due_today', 1)
            ->assertJsonPath('data.tasks.items.0.customer_name', 'Dashboard Customer')
            ->assertJsonPath('data.staff.0.open_tasks', 1);
    }

    public function test_crm_dashboard_rejects_another_farm(): void
    {
        $user = User::factory()->create(['role' => 'farmWorker', 'crm_role' => 'customer_support']);
        $farm = Farm::create(['name' => 'Accessible Farm']);
        $otherFarm = Farm::create(['name' => 'Restricted Farm']);
        $farm->users()->attach($user->id);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/crm/dashboard/overview?farm_id='.$otherFarm->id)
            ->assertForbidden();
    }

    public function test_directory_groups_accessible_farm_members_by_role(): void
    {
        $support = User::factory()->create(['role' => 'farmWorker', 'crm_role' => 'customer_support']);
        $owner = User::factory()->create(['role' => 'farmOwner']);
        $manager = User::factory()->create(['role' => 'farmManager']);
        $worker = User::factory()->create(['role' => 'farmWorker']);
        $farm = Farm::create(['name' => 'Directory Farm']);
        $farm->users()->attach([$support->id, $owner->id, $manager->id, $worker->id]);

        $this->actingAs($support, 'sanctum')
            ->getJson('/api/v1/crm/directories/overview')
            ->assertOk()
            ->assertJsonFragment(['name' => $owner->name, 'farm_name' => 'Directory Farm', 'role' => 'farmOwner'])
            ->assertJsonFragment(['name' => $manager->name, 'farm_name' => 'Directory Farm', 'role' => 'farmManager'])
            ->assertJsonFragment(['name' => $worker->name, 'farm_name' => 'Directory Farm', 'role' => 'farmWorker'])
            ->assertJsonPath('data.relationships.0.farm_name', 'Directory Farm')
            ->assertJsonPath('data.relationships.0.owner.name', $owner->name)
            ->assertJsonCount(1, 'data.relationships.0.managers')
            ->assertJsonCount(2, 'data.relationships.0.workers');
    }

    public function test_customer_orders_are_scoped_and_filterable_by_status(): void
    {
        $admin = User::factory()->create(['role' => 'farmOwner']);
        $farm = Farm::create(['name' => 'Orders Farm']);
        $farm->users()->attach($admin->id);
        $customer = Customer::create(['farm_id' => $farm->id, 'created_by' => $admin->id, 'name' => 'Order Customer']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/crm/orders', [
                'farm_id' => $farm->id,
                'customer_id' => $customer->id,
                'reference' => 'ORD-001',
                'status' => 'pending',
                'total_amount' => 12500,
                'currency' => 'KES',
                'ordered_at' => '2026-09-10',
            ])
            ->assertCreated()
            ->assertJsonPath('data.reference', 'ORD-001')
            ->assertJsonPath('data.customer_name', 'Order Customer');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/crm/orders?status=pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'pending');
    }
}
