<?php

namespace Database\Factories;

use App\Models\CodeChunk;
use App\Models\GithubInstallation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodeChunk>
 */
class CodeChunkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'github_installation_id' => GithubInstallation::factory(),
            'file_path' => 'app/'.fake()->unique()->slug(3).'.php',
            'chunk_index' => 0,
            'code_content' => "<?php\n\n".fake()->sentence()."\n",
        ];
    }

    /**
     * Configure the factory to mirror the installation's project.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (CodeChunk $chunk): void {
            if (! $chunk->project_id) {
                $chunk->forceFill(['project_id' => $chunk->installation->project_id])->save();
            }
        });
    }
}
