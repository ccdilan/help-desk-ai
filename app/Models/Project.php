<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, GithubInstallation> $installations
 * @property-read Collection<int, CodeChunk> $chunks
 */
#[Fillable(['name', 'slug', 'description'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Get all GitHub installations for this project.
     *
     * @return HasMany<GithubInstallation, $this>
     */
    public function installations(): HasMany
    {
        return $this->hasMany(GithubInstallation::class);
    }

    /**
     * Get all code chunks for this project.
     *
     * @return HasMany<CodeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(CodeChunk::class);
    }
}
