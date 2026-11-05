<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateLeadSuggestion;
use App\Models\AmoChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class AmoWebhookController extends Controller
{
    /**
     * Handle amoCRM "message[add]" webhooks for chat messages.
     *
     * amoCRM expects a fast 2xx answer, so the LLM call is deferred to a queued job.
     */
    public function __invoke(Request $request, string $secret): Response
    {
        $expectedSecret = (string) config('services.amocrm.webhook_secret');

        abort_if($expectedSecret === '' || ! hash_equals($expectedSecret, $secret), 404);

        $leadsToSuggest = [];

        foreach ($request->input('message.add', []) as $payload) {
            $leadId = $this->resolveLeadId($payload);
            $text = trim((string) ($payload['text'] ?? ''));

            if ($leadId === null || $text === '' || empty($payload['id'])) {
                continue;
            }

            $direction = ($payload['type'] ?? null) === AmoChatMessage::DirectionOutgoing
                ? AmoChatMessage::DirectionOutgoing
                : AmoChatMessage::DirectionIncoming;

            AmoChatMessage::query()->firstOrCreate(
                ['amo_message_id' => (string) $payload['id']],
                [
                    'lead_id' => $leadId,
                    'talk_id' => isset($payload['talk_id']) ? (string) $payload['talk_id'] : null,
                    'direction' => $direction,
                    'author_name' => $payload['author']['name'] ?? null,
                    'text' => $text,
                    'sent_at' => isset($payload['created_at']) ? Carbon::createFromTimestamp((int) $payload['created_at']) : now(),
                ],
            );

            if ($direction === AmoChatMessage::DirectionIncoming) {
                $leadsToSuggest[$leadId] = true;
            }
        }

        foreach (array_keys($leadsToSuggest) as $leadId) {
            GenerateLeadSuggestion::dispatch($leadId)
                ->delay(now()->addSeconds(config('services.amocrm.suggestion_delay')));
        }

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveLeadId(array $payload): ?int
    {
        if (in_array($payload['entity_type'] ?? null, ['lead', 'leads'], true) && ! empty($payload['entity_id'])) {
            return (int) $payload['entity_id'];
        }

        if ((string) ($payload['element_type'] ?? '') === '2' && ! empty($payload['element_id'])) {
            return (int) $payload['element_id'];
        }

        return null;
    }
}
