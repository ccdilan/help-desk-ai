<?php

namespace App\Services\GitHub;

use App\Contracts\FileIngestor;
use App\Models\CodeChunk;
use App\Models\GithubInstallation;
use App\Services\RepositorySyncService;

class GitHubWebhookHandler
{
    public function __construct(
        private readonly FileIngestor $ingestor,
        private readonly RepositorySyncService $sync,
    ) {}

    /**
     * Route a GitHub webhook event to its maintenance routine.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $event, array $payload): void
    {
        $installation = GithubInstallation::query()
            ->where('repository_full_name', (string) ($payload['repository']['full_name'] ?? ''))
            ->first();

        if ($installation === null) {
            return;
        }

        match ($event) {
            'push' => $this->handlePush($installation, $payload),
            'pull_request' => $this->handlePullRequest($installation, $payload),
            default => null,
        };
    }

    /**
     * Re-ingest changed files and drop chunks of removed files.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handlePush(GithubInstallation $installation, array $payload): void
    {
        if ((bool) ($payload['deleted'] ?? false)) {
            return;
        }

        $ref = (string) ($payload['after'] ?? 'HEAD');

        /** @var array<int, array<string, mixed>> $commits */
        $commits = (array) ($payload['commits'] ?? []);
        $commitList = collect($commits);

        $removed = $commitList
            ->flatMap(fn (array $commit): array => array_map(strval(...), (array) ($commit['removed'] ?? [])))
            ->unique()->values();
        $changed = $commitList->flatMap(
            fn (array $commit): array => [
                ...array_map(strval(...), (array) ($commit['added'] ?? [])),
                ...array_map(strval(...), (array) ($commit['modified'] ?? [])),
            ],
        )->unique()->reject(fn (string $path): bool => $removed->contains($path))->values();

        foreach ($removed as $path) {
            CodeChunk::query()
                ->where('github_installation_id', $installation->id)
                ->where('file_path', $path)
                ->delete();
        }

        foreach ($changed as $path) {
            $this->ingestor->ingest($installation, $path, $ref);
        }

        $installation->forceFill([
            'status' => 'synced',
            'last_synced_at' => now(),
        ])->save();
    }

    /**
     * Fully re-sync the repository when a pull request is merged.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handlePullRequest(GithubInstallation $installation, array $payload): void
    {
        $merged = (bool) ($payload['pull_request']['merged'] ?? false);
        $action = (string) ($payload['action'] ?? '');

        if ($action === 'closed' && $merged) {
            $this->sync->sync($installation);
        }
    }
}
