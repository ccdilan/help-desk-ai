<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Project;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'initial_prompt' => fake()->paragraph(),
            'status' => TicketStatus::Discovering,
        ];
    }

    /**
     * Indicate the ticket has a generated PRD and is in review.
     */
    public function reviewed(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::Review,
            'generated_prd' => "# Software Requirements Specification\n\n".fake()->paragraph(),
        ]);
    }
}
