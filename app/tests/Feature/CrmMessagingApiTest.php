<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrmMessagingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_farm_member_can_message_crm_and_crm_can_read_sender_details(): void
    {
        $owner = User::factory()->create(['role' => 'farmOwner']);
        $member = User::factory()->create(['role' => 'farmWorker']);
        $farm = Farm::create(['name' => 'Messaging Farm']);
        $farm->users()->attach([$owner->id, $member->id]);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/farms/{$farm->id}/notifications/messages", [
                'message' => 'Please help me with my subscription.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'crm_message_sent');

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/crm/notifications?farm_id={$farm->id}")
            ->assertOk()
            ->assertJsonPath('data.0.sender_id', $member->id)
            ->assertJsonPath('data.0.sender_name', $member->name)
            ->assertJsonPath('data.0.message', 'Please help me with my subscription.');

        $this->assertDatabaseHas('crm_notifications', [
            'farm_id' => $farm->id,
            'sender_id' => $member->id,
            'message' => 'Please help me with my subscription.',
        ]);
        $this->assertDatabaseHas('farm_notifications', [
            'farm_id' => $farm->id,
            'recipient_id' => $member->id,
            'type' => 'crm_message_sent',
        ]);
    }

    public function test_message_cannot_be_sent_by_a_non_member(): void
    {
        $user = User::factory()->create(['role' => 'farmWorker']);
        $farm = Farm::create(['name' => 'Private Messaging Farm']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/farms/{$farm->id}/notifications/messages", ['message' => 'Hello'])
            ->assertForbidden();

        $this->assertSame(0, DB::table('crm_notifications')->count());
    }
}
