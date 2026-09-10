<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Farm;
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
}
