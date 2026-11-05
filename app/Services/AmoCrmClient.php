<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AmoCrmClient
{
    /**
     * amoCRM error code returned when the target entity no longer exists (e.g. the lead was deleted).
     */
    private const ErrorEntityUnavailable = 226;

    private const EventOutgoingChatMessage = 'outgoing_chat_message';

    /**
     * Add a common (text) note to a lead. Returns false when the lead is gone.
     */
    public function addLeadNote(int $leadId, string $text): bool
    {
        try {
            $this->request()
                ->post("/api/v4/leads/{$leadId}/notes", [
                    ['note_type' => 'common', 'params' => ['text' => $text]],
                ])
                ->throw();
        } catch (RequestException $exception) {
            $errorCodes = array_column($exception->response->json('errors') ?? [], 'code');

            if (in_array(self::ErrorEntityUnavailable, $errorCodes, true)) {
                Log::warning('amoCRM lead is unavailable, note skipped.', ['lead_id' => $leadId]);

                return false;
            }

            throw $exception;
        }

        return true;
    }

    /**
     * Whether a manager wrote to the client in the lead's chat at or after the given moment.
     *
     * amoCRM does not deliver outgoing chat messages to the webhook, so the events feed is the
     * only source. It exposes the fact and the time of a reply, not its text.
     */
    public function hasManagerRepliedSince(int $leadId, CarbonInterface $since): bool
    {
        $response = $this->request()->get('/api/v4/events', [
            'filter[type]' => self::EventOutgoingChatMessage,
            'filter[created_at][from]' => $since->getTimestamp(),
            'limit' => 100,
        ]);

        if ($response->failed()) {
            // A hint the manager may not need is cheaper than a missing one.
            Log::warning('amoCRM events are unavailable, manager reply check skipped.', ['lead_id' => $leadId, 'status' => $response->status()]);

            return false;
        }

        // The events filter by entity does not return chat events, so the lead is matched here.
        // created_by is 0 for automatic replies (e.g. Salesbot), which do not count as a manager's answer.
        return collect($response->json('_embedded.events') ?? [])
            ->contains(fn (array $event): bool => ($event['entity_type'] ?? null) === 'lead'
                && (int) ($event['entity_id'] ?? 0) === $leadId
                && (int) ($event['created_by'] ?? 0) > 0);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('services.amocrm.base_url'))
            ->withToken(config('services.amocrm.access_token'))
            ->acceptJson()
            ->connectTimeout(30)
            ->timeout(30)
            ->retry(2, 500, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && $exception->response->serverError()), throw: false);
    }
}
