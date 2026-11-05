<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lead_id', 'amo_chat_message_id', 'client_reply', 'answered_by_knowledge_base', 'upsell_offer', 'upsell_reason', 'upsell_phrase', 'manager_tip', 'model', 'note_sent'])]
class AmoSuggestion extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lead_id' => 'integer',
            'answered_by_knowledge_base' => 'boolean',
            'note_sent' => 'boolean',
        ];
    }

    /**
     * The client message this suggestion answers.
     *
     * @return BelongsTo<AmoChatMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(AmoChatMessage::class, 'amo_chat_message_id');
    }
}
