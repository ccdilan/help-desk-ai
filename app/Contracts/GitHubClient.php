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
     * When an installation id is given, the request is authenticated with a
     * GitHub App installation access token so private repositories resolve.
     *
     * @return array<int, array{path: string, type: string}>
     */
    public function repositoryTree(string $fullName, string $ref, ?int $installationId = null): array;

    /**
     * Fetch the raw content of a single file.
     *
     * When an installation id is given, the request is authenticated with a
     * GitHub App installation access token so private repositories resolve.
     */
    public function fileContent(string $fullName, string $path, string $ref, ?int $installationId = null): ?string;
}
