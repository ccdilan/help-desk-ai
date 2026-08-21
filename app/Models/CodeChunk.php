<?php

namespace App\Models;

use App\Casts\VectorEmbedding;
use Database\Factories\CodeChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $github_installation_id
 * @property string $file_path
 * @property int $chunk_index
 * @property string $code_content
 * @property list<float>|null $embedding
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read GithubInstallation $installation
 */
#[Fillable(['project_id', 'github_installation_id', 'file_path', 'chunk_index', 'code_content', 'embedding'])]
class CodeChunk extends Model
{
    /** @use HasFactory<CodeChunkFactory> */
    use HasFactory;

    /**
     * Get the project this code chunk belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the GitHub installation this code chunk was ingested from.
     *
     * @return BelongsTo<GithubInstallation, $this>
     */
    public function installation(): BelongsTo
    {
        return $this->belongsTo(GithubInstallation::class, 'github_installation_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => VectorEmbedding::class,
        ];
    }
}
