<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketOfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    private function createBeneficiaryUser(
        string $beneficiaryNo
    ): User {
        $beneficiary = Beneficiary::create([
            'beneficiary_no' => $beneficiaryNo,
            'full_name' => 'Test Beneficiary',
            'status' => 'Active',
        ]);

        return User::factory()->create([
            'role' => 'user',
            'beneficiary_id' => $beneficiary->id,
            'password_changed' => true,
        ]);
    }

    public function test_beneficiary_can_submit_support_ticket_with_offline_request_id(): void
    {
        $user = $this->createBeneficiaryUser('BEN-9001');

        Sanctum::actingAs($user);

        $offlineRequestId = (string) Str::uuid();

        $response = $this->postJson('/api/support/tickets', [
            'title' => 'Cannot access my account',
            'type' => 'technical',
            'description' => 'I cannot access my account after logging in.',
            'offline_request_id' => $offlineRequestId,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Cannot access my account')
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('support_tickets', [
            'reporter_id' => $user->id,
            'offline_request_id' => $offlineRequestId,
            'title' => 'Cannot access my account',
        ]);

        $this->assertDatabaseCount('support_tickets', 1);
    }

    public function test_retrying_same_offline_request_does_not_create_duplicate_ticket(): void
    {
        $user = $this->createBeneficiaryUser('BEN-9002');

        Sanctum::actingAs($user);

        $offlineRequestId = (string) Str::uuid();

        $payload = [
            'title' => 'Application issue',
            'type' => 'system-bug',
            'description' => 'The application closes unexpectedly.',
            'offline_request_id' => $offlineRequestId,
        ];

        $firstResponse = $this->postJson(
            '/api/support/tickets',
            $payload
        );

        $firstResponse->assertCreated();

        $firstTicketId = $firstResponse->json('data.id');

        $secondResponse = $this->postJson(
            '/api/support/tickets',
            $payload
        );

        $secondResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $firstTicketId);

        $this->assertDatabaseCount('support_tickets', 1);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $firstTicketId,
            'reporter_id' => $user->id,
            'offline_request_id' => $offlineRequestId,
        ]);
    }

    public function test_beneficiary_cannot_view_another_beneficiary_support_tickets(): void
    {
        $firstUser = $this->createBeneficiaryUser('BEN-9003');

        Sanctum::actingAs($firstUser);

        $this->postJson('/api/support/tickets', [
            'title' => 'My private support request',
            'type' => 'other',
            'description' => 'This is my private support request.',
            'offline_request_id' => (string) Str::uuid(),
        ])->assertCreated();

        $secondUser = $this->createBeneficiaryUser('BEN-9004');

        Sanctum::actingAs($secondUser);

        $response = $this->getJson('/api/support/tickets');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data')
            ->assertJsonMissing([
                'title' => 'My private support request',
            ]);
    }

    public function test_normal_online_support_ticket_submission_still_works(): void
    {
        $user = $this->createBeneficiaryUser('BEN-9005');

        Sanctum::actingAs($user);

        $this->postJson('/api/support/tickets', [
            'title' => 'General inquiry',
            'type' => 'other',
            'description' => 'I have a question about my relief assistance.',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'General inquiry');

        $this->assertDatabaseHas('support_tickets', [
            'reporter_id' => $user->id,
            'title' => 'General inquiry',
            'offline_request_id' => null,
        ]);

        $this->assertDatabaseCount('support_tickets', 1);
    }
}
