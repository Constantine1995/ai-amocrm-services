<?php

namespace Database\Factories;

use App\Models\AmoChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AmoChatMessage>
 */
class AmoChatMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amo_message_id' => fake()->uuid(),
            'lead_id' => fake()->numberBetween(1000, 9999),
            'talk_id' => (string) fake()->numberBetween(1, 500),
            'direction' => AmoChatMessage::DirectionIncoming,
            'author_name' => fake()->name(),
            'text' => fake()->sentence(),
            'sent_at' => now(),
        ];
    }

    public function outgoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => AmoChatMessage::DirectionOutgoing,
        ]);
    }
}
