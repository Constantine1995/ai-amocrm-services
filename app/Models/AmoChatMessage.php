<?php

namespace App\Models;

use Database\Factories\AmoChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['amo_message_id', 'lead_id', 'talk_id', 'direction', 'author_name', 'text', 'sent_at'])]
class AmoChatMessage extends Model
{
    /** @use HasFactory<AmoChatMessageFactory> */
    use HasFactory;

    public const DirectionIncoming = 'incoming';

    public const DirectionOutgoing = 'outgoing';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lead_id' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function isIncoming(): bool
    {
        return $this->direction === self::DirectionIncoming;
    }

    /**
     * Scope the query to a single lead's dialog.
     *
     * @param  Builder<AmoChatMessage>  $query
     */
    public function scopeForLead(Builder $query, int $leadId): void
    {
        $query->where('lead_id', $leadId);
    }
}
