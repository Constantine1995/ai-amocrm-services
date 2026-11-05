<?php

namespace Tests\Feature;

use App\Jobs\GenerateLeadSuggestion;
use App\Models\AmoChatMessage;
use App\Services\SalesAssistant;
use App\Services\SalesSuggestion;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class GenerateLeadSuggestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.amocrm.base_url' => 'https://demo.amocrm.ru',
            'services.amocrm.access_token' => 'token',
        ]);
    }

    public function test_it_sends_the_dialog_to_the_assistant_and_adds_a_lead_note(): void
    {
        $this->fakeAmoCrm();
        AmoChatMessage::factory()->create(['lead_id' => 7, 'text' => 'Хочу кофемашину', 'sent_at' => now()->subMinutes(3)]);
        AmoChatMessage::factory()->outgoing()->create(['lead_id' => 7, 'text' => 'Какую рассматриваете?', 'sent_at' => now()->subMinutes(2)]);
        AmoChatMessage::factory()->create(['lead_id' => 7, 'text' => 'AutoCafe 3, доставка в Казань?', 'sent_at' => now()->subMinute()]);

        $this->mock(SalesAssistant::class, function (MockInterface $mock): void {
            $mock->shouldReceive('suggest')
                ->once()
                ->with([
                    ['from' => 'client', 'text' => 'Хочу кофемашину'],
                    ['from' => 'manager', 'text' => 'Какую рассматриваете?'],
                    ['from' => 'client', 'text' => 'AutoCafe 3, доставка в Казань?'],
                ])
                ->andReturn($this->suggestion());
        });

        GenerateLeadSuggestion::dispatchSync(7);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://demo.amocrm.ru/api/v4/leads/7/notes'
            && $request->hasHeader('Authorization', 'Bearer token')
            && $request[0]['note_type'] === 'common'
            && str_contains($request[0]['params']['text'], 'Доставка СДЭК 2–7 дней.')
            && str_contains($request[0]['params']['text'], 'Молочник-термос'));
    }

    public function test_deleted_lead_does_not_fail_the_job(): void
    {
        $this->fakeAmoCrm(Http::response(['errors' => [['code' => 226, 'message' => 'Error 226.']]], 400));
        AmoChatMessage::factory()->create(['lead_id' => 7]);
        $this->mock(SalesAssistant::class, fn (MockInterface $mock) => $mock->shouldReceive('suggest')->andReturn($this->suggestion()));

        GenerateLeadSuggestion::dispatchSync(7);

        $this->assertNotesSent(1);
        $this->assertDatabaseHas('amo_suggestions', ['lead_id' => 7, 'note_sent' => false]);
    }

    public function test_it_skips_when_the_manager_already_answered(): void
    {
        $this->fakeAmoCrm();
        AmoChatMessage::factory()->create(['lead_id' => 7, 'sent_at' => now()->subMinute()]);
        AmoChatMessage::factory()->outgoing()->create(['lead_id' => 7, 'sent_at' => now()]);

        $this->mock(SalesAssistant::class, fn (MockInterface $mock) => $mock->shouldNotReceive('suggest'));

        GenerateLeadSuggestion::dispatchSync(7);

        Http::assertNothingSent();
    }

    public function test_it_stores_the_suggestion_for_the_answered_message(): void
    {
        $this->fakeAmoCrm();
        config(['services.ollama.model' => 'qwen2.5:7b']);
        $message = AmoChatMessage::factory()->create(['lead_id' => 7]);
        $this->mock(SalesAssistant::class, fn (MockInterface $mock) => $mock->shouldReceive('suggest')->andReturn($this->suggestion()));

        GenerateLeadSuggestion::dispatchSync(7);

        $this->assertDatabaseCount('amo_suggestions', 1);
        $this->assertDatabaseHas('amo_suggestions', [
            'lead_id' => 7,
            'amo_chat_message_id' => $message->id,
            'client_reply' => 'Доставка СДЭК 2–7 дней.',
            'answered_by_knowledge_base' => true,
            'upsell_offer' => 'Молочник-термос — 2 400 ₽',
            'upsell_reason' => 'Клиент пьёт капучино.',
            'upsell_phrase' => 'Добавить молочник?',
            'manager_tip' => 'Уточните адрес.',
            'model' => 'qwen2.5:7b',
            'note_sent' => true,
        ]);
    }

    public function test_repeated_job_for_the_same_message_does_not_add_a_second_note(): void
    {
        $this->fakeAmoCrm();
        AmoChatMessage::factory()->create(['lead_id' => 7]);
        $this->mock(SalesAssistant::class, fn (MockInterface $mock) => $mock->shouldReceive('suggest')->once()->andReturn($this->suggestion()));

        GenerateLeadSuggestion::dispatchSync(7);
        GenerateLeadSuggestion::dispatchSync(7);

        $this->assertNotesSent(1);
        $this->assertDatabaseCount('amo_suggestions', 1);
    }

    public function test_failed_note_delivery_stores_nothing_so_the_retry_starts_clean(): void
    {
        $this->fakeAmoCrm(Http::response([], 422));
        AmoChatMessage::factory()->create(['lead_id' => 7]);
        $this->mock(SalesAssistant::class, fn (MockInterface $mock) => $mock->shouldReceive('suggest')->andReturn($this->suggestion()));

        try {
            GenerateLeadSuggestion::dispatchSync(7);
            $this->fail('The job should rethrow the amoCRM error.');
        } catch (RequestException) {
            $this->assertDatabaseCount('amo_suggestions', 0);
        }
    }

    public function test_message_arriving_during_generation_gets_a_single_note_from_the_next_job(): void
    {
        $this->fakeAmoCrm();
        AmoChatMessage::factory()->create(['lead_id' => 7, 'sent_at' => now()->subMinute()]);

        $this->mock(SalesAssistant::class, function (MockInterface $mock): void {
            $mock->shouldReceive('suggest')->once()->andReturnUsing(function (): SalesSuggestion {
                AmoChatMessage::factory()->create(['lead_id' => 7, 'sent_at' => now()]);

                return $this->suggestion();
            });
            $mock->shouldReceive('suggest')->once()->andReturn($this->suggestion());
        });

        GenerateLeadSuggestion::dispatchSync(7);

        $this->assertNotesSent(0);
        $this->assertDatabaseCount('amo_suggestions', 0);

        GenerateLeadSuggestion::dispatchSync(7);

        $this->assertNotesSent(1);
        $this->assertDatabaseCount('amo_suggestions', 1);
    }

    public function test_it_skips_when_amocrm_reports_a_manager_reply_after_the_last_client_message(): void
    {
        $this->fakeAmoCrm(events: [$this->outgoingChatEvent(7)]);
        $message = AmoChatMessage::factory()->create(['lead_id' => 7]);
        $this->mock(SalesAssistant::class, fn (MockInterface $mock) => $mock->shouldNotReceive('suggest'));

        GenerateLeadSuggestion::dispatchSync(7);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://demo.amocrm.ru/api/v4/events')
            && $request['filter[type]'] === 'outgoing_chat_message'
            && (int) $request['filter[created_at][from]'] === $message->sent_at->getTimestamp());
        $this->assertNotesSent(0);
        $this->assertDatabaseCount('amo_suggestions', 0);
    }

    public function test_replies_in_other_leads_and_automatic_replies_do_not_suppress_the_note(): void
    {
        $this->fakeAmoCrm(events: [$this->outgoingChatEvent(8), $this->outgoingChatEvent(7, createdBy: 0)]);
        AmoChatMessage::factory()->create(['lead_id' => 7]);
        $this->mock(SalesAssistant::class, fn (MockInterface $mock) => $mock->shouldReceive('suggest')->once()->andReturn($this->suggestion()));

        GenerateLeadSuggestion::dispatchSync(7);

        $this->assertNotesSent(1);
    }

    public function test_note_is_still_sent_when_the_events_feed_is_unavailable(): void
    {
        Http::fake([
            'demo.amocrm.ru/api/v4/events*' => Http::response([], 403),
            'demo.amocrm.ru/*' => Http::response([], 200),
        ]);
        AmoChatMessage::factory()->create(['lead_id' => 7]);
        $this->mock(SalesAssistant::class, fn (MockInterface $mock) => $mock->shouldReceive('suggest')->once()->andReturn($this->suggestion()));

        GenerateLeadSuggestion::dispatchSync(7);

        $this->assertNotesSent(1);
    }

    /**
     * Stubs registered first win in Http::fake, so each test declares its own amoCRM responses.
     *
     * @param  list<array<string, mixed>>  $events
     */
    private function fakeAmoCrm(?PromiseInterface $noteResponse = null, array $events = []): void
    {
        Http::fake([
            'demo.amocrm.ru/api/v4/events*' => $events === []
                ? Http::response(null, 204)
                : Http::response(['_embedded' => ['events' => $events]]),
            'demo.amocrm.ru/*' => $noteResponse ?? Http::response([], 200),
        ]);
    }

    private function assertNotesSent(int $count): void
    {
        $this->assertCount($count, Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/notes')));
    }

    /**
     * @return array<string, mixed>
     */
    private function outgoingChatEvent(int $leadId, int $createdBy = 14290794): array
    {
        return ['type' => 'outgoing_chat_message', 'entity_type' => 'lead', 'entity_id' => $leadId, 'created_by' => $createdBy];
    }

    private function suggestion(): SalesSuggestion
    {
        return SalesSuggestion::fromArray([
            'client_reply' => 'Доставка СДЭК 2–7 дней.',
            'answered_by_knowledge_base' => true,
            'upsell_offer' => 'Молочник-термос — 2 400 ₽',
            'upsell_reason' => 'Клиент пьёт капучино.',
            'upsell_phrase' => 'Добавить молочник?',
            'manager_tip' => 'Уточните адрес.',
        ]);
    }
}
