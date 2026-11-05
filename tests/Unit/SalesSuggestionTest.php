<?php

namespace Tests\Unit;

use App\Services\SalesSuggestion;
use PHPUnit\Framework\TestCase;

class SalesSuggestionTest extends TestCase
{
    public function test_note_contains_reply_and_upsell_blocks(): void
    {
        $note = $this->makeSuggestion(upsellOffer: 'Набор для ухода — 1 900 ₽')->toNoteText();

        $this->assertStringContainsString("ОТВЕТ КЛИЕНТУ (черновик):\nДобрый день!", $note);
        $this->assertStringContainsString('Что предложить: Набор для ухода — 1 900 ₽', $note);
        $this->assertStringContainsString('Как сказать: Хотите набор?', $note);
        $this->assertStringNotContainsString('В базе знаний нет', $note);
    }

    public function test_empty_upsell_offer_means_no_upsell(): void
    {
        $suggestion = $this->makeSuggestion(upsellOffer: '  ', isAnsweredByKnowledgeBase: false);

        $this->assertNull($suggestion->upsellOffer);
        $this->assertStringContainsString('Сейчас допродажу лучше не предлагать.', $suggestion->toNoteText());
        $this->assertStringContainsString('В базе знаний нет точного ответа', $suggestion->toNoteText());
    }

    private function makeSuggestion(string $upsellOffer, bool $isAnsweredByKnowledgeBase = true): SalesSuggestion
    {
        return SalesSuggestion::fromArray([
            'client_reply' => 'Добрый день!',
            'answered_by_knowledge_base' => $isAnsweredByKnowledgeBase,
            'upsell_offer' => $upsellOffer,
            'upsell_reason' => 'Клиент купил машину.',
            'upsell_phrase' => 'Хотите набор?',
            'manager_tip' => 'Всё хорошо.',
        ]);
    }
}
