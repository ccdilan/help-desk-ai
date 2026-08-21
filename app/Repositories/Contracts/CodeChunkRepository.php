<?php

namespace App\Repositories\Contracts;

use App\Models\CodeChunk;
use Illuminate\Support\Collection;

interface CodeChunkRepository
{
    /**
     * Find the code chunks most similar to the given embedding within a project,
     * ordered by ascending cosine distance.
     *
     * @param  list<float>  $embedding
     * @return Collection<int, array{chunk: CodeChunk, distance: float}>
     */
    public function searchSimilar(int $projectId, array $embedding, int $limit = 8): Collection;
}
