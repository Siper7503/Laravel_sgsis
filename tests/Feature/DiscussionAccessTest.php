<?php

namespace Tests\Feature;

use App\Models\Discussion;
use App\Models\DiscussionMessage;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscussionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_create_discussion_with_admin_and_exchange_messages(): void
    {
        $agent = User::factory()->create(['role' => UserRole::Agent]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $discussionId = $this->actingAs($agent)
            ->postJson('/api/discussions', [
                'subject' => 'Coordination de rentrée',
                'participant_ids' => [$admin->id],
                'message' => 'Bonjour, échangeons sur la rentrée.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.subject', 'Coordination de rentrée')
            ->assertJsonFragment(['id' => $admin->id, 'role' => 'admin'])
            ->assertJsonFragment(['id' => $agent->id, 'role' => 'agent'])
            ->assertJsonPath('data.messages.0.body', 'Bonjour, échangeons sur la rentrée.')
            ->json('data.id');

        $this->actingAs($admin)
            ->postJson('/api/discussions/'.$discussionId.'/messages', [
                'body' => 'Bien reçu, merci.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Bien reçu, merci.')
            ->assertJsonPath('data.sender.id', $admin->id);

        $this->assertDatabaseCount('discussions', 1);
        $this->assertDatabaseCount('discussion_messages', 2);
        $this->assertDatabaseCount('discussion_participants', 2);
    }

    public function test_superadmin_and_usager_cannot_create_or_view_discussions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $usager = User::factory()->create(['role' => UserRole::Usager]);
        $discussion = Discussion::create(['created_by' => $admin->id, 'subject' => 'Privée']);
        $discussion->participants()->attach($admin->id);
        DiscussionMessage::create([
            'discussion_id' => $discussion->id,
            'sender_id' => $admin->id,
            'body' => 'Contenu privé',
        ]);

        $this->actingAs($superadmin)
            ->getJson('/api/discussions')
            ->assertForbidden();
        $this->actingAs($usager)
            ->postJson('/api/discussions', [
                'subject' => 'Interdit',
                'participant_ids' => [$admin->id],
                'message' => 'Texte',
            ])
            ->assertForbidden();
        $this->actingAs($superadmin)
            ->getJson('/api/discussions/'.$discussion->id)
            ->assertForbidden();
    }

    public function test_non_participant_cannot_view_or_send_messages_to_discussion(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $agent = User::factory()->create(['role' => UserRole::Agent]);
        $otherAgent = User::factory()->create(['role' => UserRole::Agent]);
        $discussion = Discussion::create(['created_by' => $agent->id, 'subject' => 'Privée']);
        $discussion->participants()->attach($agent->id);

        $this->actingAs($otherAgent)
            ->getJson('/api/discussions/'.$discussion->id)
            ->assertNotFound();
        $this->actingAs($otherAgent)
            ->postJson('/api/discussions/'.$discussion->id.'/messages', ['body' => 'Intrusion'])
            ->assertNotFound();
        $this->actingAs($agent)
            ->getJson('/api/discussions')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');

        $this->actingAs($agent)
            ->getJson('/api/discussions/'.$discussion->id)
            ->assertOk()
            ->assertJsonPath('data.discussion.id', $discussion->id)
            ->assertJsonCount(0, 'data.messages.data');

        $this->assertDatabaseCount('discussion_messages', 0);
    }

    public function test_agent_cannot_add_superadmin_as_discussion_participant(): void
    {
        $agent = User::factory()->create(['role' => UserRole::Agent]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);

        $this->actingAs($agent)
            ->postJson('/api/discussions', [
                'subject' => 'Sujet',
                'participant_ids' => [$superadmin->id],
                'message' => 'Message',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('participant_ids.0');

        $this->assertDatabaseCount('discussions', 0);
    }

    public function test_agent_cannot_create_discussion_with_only_themselves_as_participant(): void
    {
        $agent = User::factory()->create(['role' => UserRole::Agent]);

        $this->actingAs($agent)
            ->postJson('/api/discussions', [
                'subject' => 'Sujet sans interlocuteur',
                'participant_ids' => [$agent->id],
                'message' => 'Message',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('participant_ids.0');

        $this->assertDatabaseCount('discussions', 0);
    }
}
