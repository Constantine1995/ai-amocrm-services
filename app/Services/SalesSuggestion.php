<?php

namespace App\Services;

class SalesSuggestion
{
    public function __construct(
        public readonly string $clientReply,
        public readonly bool $isAnsweredByKnowledgeBase,
        public readonly ?string $upsellOffer,
        public readonly string $upsellReason,
        public readonly string $upsellPhrase,
        public readonly string $managerTip,
    ) {}

    /**
     * @param  array{client_reply: string, answered_by_knowledge_base: bool, upsell_offer: string, upsell_reason: string, upsell_phrase: string, manager_tip: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            clientReply: $data['client_reply'],
            isAnsweredByKnowledgeBase: $data['answered_by_knowledge_base'],
            upsellOffer: trim($data['upsell_offer']) === '' ? null : $data['upsell_offer'],
            upsellReason: $data['upsell_reason'],
            upsellPhrase: $data['upsell_phrase'],
            managerTip: $data['manager_tip'],
        );
    }

    /**
     * Render the suggestion as a plain-text amoCRM note.
     */
    public function toNoteText(): string
    {
        $lines = ['🤖 AI-помощник', '', '💬 ОТВЕТ КЛИЕНТУ (черновик):', $this->clientReply];

        if (! $this->isAnsweredByKnowledgeBase) {
            $lines[] = '⚠️ В базе знаний нет точного ответа — проверьте перед отправкой.';
        }

        $lines[] = '';
        $lines[] = '💰 ПОДСКАЗКА ПО ДОПРОДАЖЕ:';

        $lines[] = $this->upsellOffer === null
            ? 'Сейчас допродажу лучше не предлагать.'
            : 'Что предложить: '.$this->upsellOffer;

        if (trim($this->upsellReason) !== '') {
            $lines[] = 'Почему: '.$this->upsellReason;
        }

        if ($this->upsellOffer !== null && trim($this->upsellPhrase) !== '') {
            $lines[] = 'Как сказать: '.$this->upsellPhrase;
        }

        $lines[] = '';
        $lines[] = '📝 По работе менеджера: '.$this->managerTip;

        return implode("\n", $lines);
    }
}
