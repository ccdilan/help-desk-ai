<?php

namespace App\Contracts;

interface GitHubClient
{
    /**
     * Exchange an OAuth user authorization code for a user access token.
     */
    public function exchangeUserCode(string $code): string;

    /**
     * List repositories of a GitHub App installation, keyed by repository fields.
     *
     * @return array<int, array{id: int, full_name: string, default_branch: string}>
     */
    public function installationRepositories(int $installationId, string $token): array;

    /**
     * Fetch the recursive file tree of a repository.
     *
     * @return array<int, array{path: string, type: string}>
     */
    public function repositoryTree(string $fullName, string $ref): array;

    /**
     * Fetch the raw content of a single file.
     */
    public function fileContent(string $fullName, string $path, string $ref): ?string;
}
