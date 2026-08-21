<?php

namespace App\Services\Ingestion;

use App\Contracts\EmbeddingClient;
use App\Contracts\FileIngestor;
use App\Contracts\GitHubClient;
use App\Models\CodeChunk;
use App\Models\GithubInstallation;
use App\Services\Ai\CodeChunker;
use Illuminate\Support\Facades\DB;

class ChunkingFileIngestor implements FileIngestor
{
    public function __construct(
        private readonly GitHubClient $github,
        private readonly CodeChunker $chunker,
        private readonly EmbeddingClient $embeddings,
    ) {}

    /**
     * Fetch, chunk, vectorize and upsert a single repository file.
     */
    public function ingest(GithubInstallation $installation, string $path, string $ref): void
    {
        $content = $this->github->fileContent($installation->repository_full_name, $path, $ref, (int) $installation->installation_id);

        if ($content === null || trim($content) === '') {
            return;
        }

        $chunks = $this->chunker->chunk($content);

        if ($chunks === []) {
            return;
        }

        $vectors = $this->embeddings->embedAll($chunks);

        DB::transaction(function () use ($installation, $path, $chunks, $vectors): void {
            CodeChunk::query()
                ->where('github_installation_id', $installation->id)
                ->where('file_path', $path)
                ->delete();

            foreach ($chunks as $index => $chunk) {
                CodeChunk::query()->create([
                    'github_installation_id' => $installation->id,
                    'project_id' => $installation->project_id,
                    'file_path' => $path,
                    'chunk_index' => $index,
                    'code_content' => $chunk,
                    'embedding' => $vectors[$index],
                ]);
            }
        });
    }
}
