<?php

namespace App\Repositories\Eloquent;

use App\Models\CodeChunk;
use App\Repositories\Contracts\CodeChunkRepository as CodeChunkRepositoryContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CodeChunkRepository implements CodeChunkRepositoryContract
{
    /**
     * Find the code chunks most similar to the given embedding within a project,
     * ordered by ascending cosine distance.
     *
     * @param  list<float>  $embedding
     * @return Collection<int, array{chunk: CodeChunk, distance: float}>
     */
    public function searchSimilar(int $projectId, array $embedding, int $limit = 8): Collection
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Vector similarity search requires PostgreSQL with pgvector.');
        }

        $literal = '['.implode(',', $embedding).']';

        $chunks = CodeChunk::query()
            ->where('project_id', $projectId)
            ->whereNotNull('embedding')
            ->select('code_chunks.*')
            ->addSelect(DB::raw('code_chunks.embedding <=> ?::vector AS distance'))
            ->addBinding($literal, 'select')
            ->orderBy('distance')
            ->limit($limit)
            ->get();

        return $chunks->map(fn (CodeChunk $chunk): array => [
            'chunk' => $chunk,
            'distance' => (float) $chunk->distance,
        ]);
    }
}
