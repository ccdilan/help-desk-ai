<?php

namespace App\Models;

use Database\Factories\GithubInstallationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $installation_id
 * @property int $repository_id
 * @property string $repository_full_name
 * @property string $status
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read Collection<int, CodeChunk> $chunks
 */
#[Fillable(['project_id', 'installation_id', 'repository_id', 'repository_full_name', 'status', 'last_synced_at'])]
class GithubInstallation extends Model
{
    /** @use HasFactory<GithubInstallationFactory> */
    use HasFactory;

    /**
     * Get the project this installation belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get all code chunks ingested from this installation's repository.
     *
     * @return HasMany<CodeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(CodeChunk::class, 'github_installation_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }
}
