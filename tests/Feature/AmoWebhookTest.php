<?php

namespace Tests\Feature;

use App\Jobs\GenerateLeadSuggestion;
use App\Models\AmoChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AmoWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.amocrm.webhook_secret' => 'top-secret']);
        Queue::fake();
    }

    public function test_incoming_message_is_stored_and_suggestion_is_queued(): void
    {
        $this->post('/webhooks/amocrm/top-secret', $this->messagePayload(type: 'incoming', text: 'Сколько стоит доставка?'))
            ->assertNoContent();

        $this->assertDatabaseHas('amo_chat_messages', [
            'amo_message_id' => 'msg-1',
            'lead_id' => 555,
            'direction' => AmoChatMessage::DirectionIncoming,
            'text' => 'Сколько стоит доставка?',
        ]);
        Queue::assertPushed(GenerateLeadSuggestion::class, fn (GenerateLeadSuggestion $job) => $job->leadId === 555);
    }

    public function test_outgoing_manager_message_is_stored_without_queueing_a_suggestion(): void
    {
        $this->post('/webhooks/amocrm/top-secret', $this->messagePayload(type: 'outgoing', text: 'Здравствуйте!'))
            ->assertNoContent();

        $this->assertDatabaseHas('amo_chat_messages', ['direction' => AmoChatMessage::DirectionOutgoing]);
        Queue::assertNothingPushed();
    }

    public function test_repeated_webhook_delivery_does_not_duplicate_messages(): void
    {
        $this->post('/webhooks/amocrm/top-secret', $this->messagePayload(type: 'incoming', text: 'Привет'));
        $this->post('/webhooks/amocrm/top-secret', $this->messagePayload(type: 'incoming', text: 'Привет'));

        $this->assertDatabaseCount('amo_chat_messages', 1);
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $this->post('/webhooks/amocrm/wrong', $this->messagePayload(type: 'incoming', text: 'Привет'))
            ->assertNotFound();

        $this->assertDatabaseCount('amo_chat_messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_message_without_lead_is_ignored(): void
    {
        $payload = $this->messagePayload(type: 'incoming', text: 'Привет');
        $payload['message']['add'][0]['entity_type'] = 'contact';
        $payload['message']['add'][0]['element_type'] = '1';

        $this->post('/webhooks/amocrm/top-secret', $payload)->assertNoContent();

        $this->assertDatabaseCount('amo_chat_messages', 0);
        Queue::assertNothingPushed();
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(string $type, string $text): array
    {
        return [
            'message' => [
                'add' => [[
                    'id' => 'msg-1',
                    'chat_id' => 'chat-1',
                    'talk_id' => '42',
                    'contact_id' => '100',
                    'text' => $text,
                    'created_at' => '1790000000',
                    'element_type' => '2',
                    'entity_type' => 'lead',
                    'element_id' => '555',
                    'entity_id' => '555',
                    'type' => $type,
                    'author' => ['id' => 'a-1', 'type' => 'external', 'name' => 'Анна'],
                ]],
            ],
            'account' => ['id' => '1', 'subdomain' => 'demo'],
        ];
    }
}
