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

    public function test_crm_reply_is_delivered_back_to_the_app_sender(): void
    {
        $owner = User::factory()->create(['role' => 'farmOwner']);
        $member = User::factory()->create(['role' => 'farmWorker']);
        $farm = Farm::create(['name' => 'Round Trip Messaging Farm']);
        $farm->users()->attach([$owner->id, $member->id]);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/farms/{$farm->id}/notifications/messages", [
                'message' => 'Can you confirm my delivery date?',
            ])
            ->assertCreated();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/crm/notifications', [
                'farm_id' => $farm->id,
                'recipient_id' => $member->id,
                'message' => 'Your delivery is confirmed for Friday.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.message', 'Your delivery is confirmed for Friday.');

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/v1/farms/{$farm->id}/notifications")
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'crm_message',
                'body' => 'Your delivery is confirmed for Friday.',
            ]);

        $this->assertDatabaseHas('support_messages', [
            'conversation_id' => DB::table('support_conversations')->where('farm_id', $farm->id)->value('id'),
            'sender_id' => $owner->id,
            'sender_role' => 'crm',
            'body' => 'Your delivery is confirmed for Friday.',
        ]);
        $this->assertDatabaseHas('communication_broadcasts', [
            'farm_id' => $farm->id,
            'sender_id' => $owner->id,
            'recipient_id' => $member->id,
            'audience' => 'individual',
        ]);
    }

    public function test_support_thread_tracks_agent_identity_read_state_and_assignment(): void
    {
        $owner = User::factory()->create(['role' => 'farmOwner']);
        $support = User::factory()->create(['role' => 'farmWorker', 'crm_role' => 'customer_support']);
        $member = User::factory()->create(['role' => 'farmWorker']);
        $farm = Farm::create(['name' => 'Threaded Support Farm']);
        $farm->users()->attach([$owner->id, $support->id, $member->id]);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/farms/{$farm->id}/support-conversation/messages", [
                'message' => 'I need help with my account.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.messages.0.sender_name', $member->name);

        $conversationId = DB::table('support_conversations')
            ->where('farm_id', $farm->id)
            ->where('app_user_id', $member->id)
            ->value('id');

        $this->actingAs($support, 'sanctum')
            ->getJson("/api/v1/crm/support-conversations?farm_id={$farm->id}")
            ->assertOk()
            ->assertJsonPath('data.0.customer.name', $member->name)
            ->assertJsonPath('data.0.unread_count', 1);

        $this->actingAs($support, 'sanctum')
            ->patchJson("/api/v1/crm/support-conversations/{$conversationId}/read")
            ->assertOk();

        $this->actingAs($support, 'sanctum')
            ->patchJson("/api/v1/crm/support-conversations/{$conversationId}", [
                'assigned_user_id' => $support->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_agent.name', $support->name);

        $this->actingAs($support, 'sanctum')
            ->postJson("/api/v1/crm/support-conversations/{$conversationId}/messages", [
                'message' => 'I am Alex from customer support. I will help you.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sender_name', $support->name);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/v1/farms/{$farm->id}/support-conversation")
            ->assertOk()
            ->assertJsonPath('data.messages.1.sender_name', $support->name)
            ->assertJsonPath('data.assigned_agent.name', $support->name);

        $this->actingAs($member, 'sanctum')
            ->patchJson("/api/v1/farms/{$farm->id}/support-conversation/read")
            ->assertOk();

        $this->actingAs($support, 'sanctum')
            ->getJson("/api/v1/crm/support-conversations/{$conversationId}")
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_farm_broadcast_is_fanned_out_only_to_other_app_members(): void
    {
        $owner = User::factory()->create(['role' => 'farmOwner']);
        $member = User::factory()->create(['role' => 'farmWorker']);
        $crmStaff = User::factory()->create(['role' => 'farmWorker', 'crm_role' => 'customer_support']);
        $outsider = User::factory()->create(['role' => 'farmWorker']);
        $farm = Farm::create(['name' => 'Broadcast Farm']);
        $farm->users()->attach([$owner->id, $member->id, $crmStaff->id]);
        $otherFarm = Farm::create(['name' => 'Other Farm']);
        $otherFarm->users()->attach($outsider->id);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/crm/notifications', [
                'farm_id' => $farm->id,
                'message' => 'Farm meeting tomorrow at 9.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.audience', 'farm')
            ->assertJsonPath('data.recipient_count', 1);

        $this->assertDatabaseHas('farm_notifications', [
            'farm_id' => $farm->id,
            'recipient_id' => $member->id,
            'body' => 'Farm meeting tomorrow at 9.',
        ]);
        $this->assertDatabaseMissing('farm_notifications', [
            'farm_id' => $farm->id,
            'recipient_id' => $outsider->id,
            'body' => 'Farm meeting tomorrow at 9.',
        ]);
        $this->assertDatabaseMissing('farm_notifications', [
            'farm_id' => $farm->id,
            'recipient_id' => $crmStaff->id,
            'body' => 'Farm meeting tomorrow at 9.',
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/v1/farms/{$farm->id}/notifications")
            ->assertOk()
            ->assertJsonFragment(['body' => 'Farm meeting tomorrow at 9.']);
    }

    public function test_support_conversation_and_broadcast_respect_farm_authorization(): void
    {
        $owner = User::factory()->create(['role' => 'farmOwner']);
        $member = User::factory()->create(['role' => 'farmWorker']);
        $farm = Farm::create(['name' => 'Private Conversation Farm']);
        $farm->users()->attach($owner->id);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/farms/{$farm->id}/support-conversation/messages", ['message' => 'Unauthorized'])
            ->assertForbidden();

        $support = User::factory()->create(['role' => 'farmWorker', 'crm_role' => 'customer_support']);
        $farm->users()->attach($support->id);
        $this->actingAs($support, 'sanctum')
            ->postJson("/api/v1/farms/{$farm->id}/support-conversation/messages", ['message' => 'CRM account cannot impersonate an app user.'])
            ->assertForbidden();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/crm/notifications', [
                'farm_id' => $farm->id,
                'recipient_id' => $member->id,
                'message' => 'Should not deliver.',
            ])
            ->assertUnprocessable();
    }
}
