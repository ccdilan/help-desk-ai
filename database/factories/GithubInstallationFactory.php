<?php

namespace Database\Factories;

use App\Models\GithubInstallation;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GithubInstallation>
 */
class GithubInstallationFactory extends Factory
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
            'installation_id' => (string) fake()->unique()->numberBetween(10000000, 99999999),
            'repository_id' => fake()->unique()->numberBetween(10000000, 99999999),
            'repository_full_name' => fake()->userName().'/'.$this->repositoryName(),
            'status' => 'pending',
            'last_synced_at' => null,
        ];
    }

    /**
     * Indicate that the repository has been fully synced.
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'synced',
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Generate a repository name.
     */
    private function repositoryName(): string
    {
        return Str::kebab(fake()->unique()->domainWord().' '.fake()->word());
    }
}
