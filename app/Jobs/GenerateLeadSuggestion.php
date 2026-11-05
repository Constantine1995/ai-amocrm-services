<?php

namespace App\Jobs;

use App\Models\AmoChatMessage;
use App\Models\AmoSuggestion;
use App\Services\AmoCrmClient;
use App\Services\SalesAssistant;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateLeadSuggestion implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * A local model on a laptop can take a while to answer.
     */
    public int $timeout = 240;

    public int $uniqueFor = 120;

    public function __construct(public int $leadId) {}

    /**
     * One pending job per lead, so a burst of client messages yields a single note.
     */
    public function uniqueId(): string
    {
        return (string) $this->leadId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(SalesAssistant $assistant, AmoCrmClient $amoCrm): void
    {
        $messages = AmoChatMessage::query()
            ->forLead($this->leadId)
            ->latest('sent_at')
            ->latest('id')
            ->limit(config('services.amocrm.history_limit'))
            ->get()
            ->reverse()
            ->values();

        if ($messages->isEmpty() || ! $messages->last()->isIncoming()) {
            return;
        }

        $lastMessage = $messages->last();

        // A redelivered webhook re-queues the job; the message it would answer already has a suggestion.
        if (AmoSuggestion::query()->where('amo_chat_message_id', $lastMessage->id)->exists()) {
            return;
        }

        if ($amoCrm->hasManagerRepliedSince($this->leadId, $lastMessage->sent_at)) {
            return;
        }

        $dialog = $messages
            ->map(fn (AmoChatMessage $message): array => [
                'from' => $message->isIncoming() ? 'client' : 'manager',
                'text' => $message->text,
            ])
            ->all();

        $suggestion = $assistant->suggest($dialog);

        // The model is slow: a message that arrived meanwhile has queued its own job, so this answer is stale.
        if ($this->latestMessageId() !== $lastMessage->id) {
            return;
        }

        // Same for the manager: if they answered while the model was thinking, the hint is no longer needed.
        if ($amoCrm->hasManagerRepliedSince($this->leadId, $lastMessage->sent_at)) {
            return;
        }

        $isNoteSent = $amoCrm->addLeadNote($this->leadId, $suggestion->toNoteText());

        // Saved only after amoCRM accepted (or definitively rejected) the note, so a failed attempt is retried in full.
        AmoSuggestion::query()->create([
            'lead_id' => $this->leadId,
            'amo_chat_message_id' => $lastMessage->id,
            'client_reply' => $suggestion->clientReply,
            'answered_by_knowledge_base' => $suggestion->isAnsweredByKnowledgeBase,
            'upsell_offer' => $suggestion->upsellOffer,
            'upsell_reason' => $suggestion->upsellReason,
            'upsell_phrase' => $suggestion->upsellPhrase,
            'manager_tip' => $suggestion->managerTip,
            'model' => config('services.ollama.model'),
            'note_sent' => $isNoteSent,
        ]);
    }

    private function latestMessageId(): ?int
    {
        return AmoChatMessage::query()
            ->forLead($this->leadId)
            ->latest('sent_at')
            ->latest('id')
            ->value('id');
    }
}
