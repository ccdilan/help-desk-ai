<?php

namespace App\Repositories\Eloquent;

use App\Models\CodeChunk;
use App\Repositories\Contracts\CodeChunkRepository as CodeChunkRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CodeChunkRepository implements CodeChunkRepositoryContract
{
    /**
     * Find the code chunks most similar to the given embedding within a project,
     * ordered by ascending cosine distance. When an installation id is given,
     * only chunks ingested from that repository are considered.
     *
     * @param  list<float>  $embedding
     * @return Collection<int, array{chunk: CodeChunk, distance: float}>
     */
    public function searchSimilar(int $projectId, array $embedding, int $limit = 8, ?int $installationId = null): Collection
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Vector similarity search requires PostgreSQL with pgvector.');
        }

        Log::channel('rag')->info('Vector similarity search started', [
            'project_id' => $projectId,
            'installation_id' => $installationId,
            'limit' => $limit,
            'embedding_dimensions' => count($embedding),
        ]);

        $literal = '['.implode(',', $embedding).']';

        $chunks = CodeChunk::query()
            ->where('project_id', $projectId)
            ->when($installationId !== null, fn (Builder $query): Builder => $query->where('github_installation_id', $installationId))
            ->whereNotNull('embedding')
            ->select('code_chunks.*')
            ->addSelect(DB::raw('code_chunks.embedding <=> ?::vector AS distance'))
            ->addBinding($literal, 'select')
            ->orderBy('distance')
            ->limit($limit)
            ->get();

        $results = $chunks->map(fn (CodeChunk $chunk): array => [
            'chunk' => $chunk,
            'distance' => (float) $chunk->distance,
        ]);

        Log::channel('rag')->info('Vector similarity search completed', [
            'project_id' => $projectId,
            'installation_id' => $installationId,
            'result_count' => $results->count(),
            'results' => $results->map(fn (array $result): array => [
                'file_path' => $result['chunk']->file_path,
                'chunk_index' => $result['chunk']->chunk_index,
                'distance' => $result['distance'],
            ])->all(),
        ]);

        return $results;
    }
}
