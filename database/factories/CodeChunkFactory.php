<?php

namespace Database\Factories;

use App\Models\CodeChunk;
use App\Models\Project;
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
            'project_id' => Project::factory(),
            'file_path' => 'app/'.fake()->unique()->slug(3).'.php',
            'chunk_index' => 0,
            'code_content' => "<?php\n\n".fake()->sentence()."\n",
        ];
    }
}
