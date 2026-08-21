<?php

namespace App\Services;

use App\Contracts\FileIngestor;
use App\Contracts\GitHubClient;
use App\Models\CodeChunk;
use App\Models\GithubInstallation;
use Throwable;

class RepositorySyncService
{
    /**
     * File extensions eligible for ingestion.
     *
     * @var list<string>
     */
    private const SUPPORTED_EXTENSIONS = ['php', 'js', 'ts', 'jsx', 'tsx', 'vue'];

    public function __construct(
        private readonly GitHubClient $github,
        private readonly FileIngestor $ingestor,
    ) {}

    /**
     * Fetch the repository structure and ingest every supported file.
     */
    public function sync(GithubInstallation $installation): void
    {
        $installation->forceFill(['status' => 'syncing'])->save();

        try {
            $tree = $this->github->repositoryTree($installation->repository_full_name, 'HEAD');

            $paths = collect($tree)
                ->filter(fn (array $entry): bool => $entry['type'] === 'blob')
                ->map(fn (array $entry): string => $entry['path'])
                ->filter($this->isSupported(...))
                ->values();

            CodeChunk::query()
                ->where('github_installation_id', $installation->id)
                ->whereNotIn('file_path', $paths->all())
                ->delete();

            foreach ($paths as $path) {
                $this->ingestor->ingest($installation, $path, 'HEAD');
            }

            $installation->forceFill([
                'status' => 'synced',
                'last_synced_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $installation->forceFill(['status' => 'failed'])->save();

            report($exception);
        }
    }

    /**
     * Determine whether a file path should be ingested.
     */
    private function isSupported(string $path): bool
    {
        if (preg_match('#(^|/)(vendor|node_modules|dist|build|storage)/#', $path) === 1) {
            return false;
        }

        return in_array(pathinfo($path, PATHINFO_EXTENSION), self::SUPPORTED_EXTENSIONS, true);
    }
}
