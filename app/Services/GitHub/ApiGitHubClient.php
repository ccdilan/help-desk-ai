<?php

namespace App\Services\GitHub;

use App\Contracts\GitHubClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class ApiGitHubClient implements GitHubClient
{
    private const API_BASE = 'https://api.github.com';

    /**
     * Exchange an OAuth user authorization code for a user access token.
     */
    public function exchangeUserCode(string $code): string
    {
        $response = Http::asJson()->accept('application/json')
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => (string) config('services.github.client_id'),
                'client_secret' => (string) config('services.github.client_secret'),
                'code' => $code,
            ])
            ->throw()
            ->json();

        return (string) ($response['access_token'] ?? '');
    }

    /**
     * List repositories of a GitHub App installation.
     *
     * @return array<int, array{id: int, full_name: string, default_branch: string}>
     */
    public function installationRepositories(int $installationId, string $token): array
    {
        $repositories = $this->request($token)
            ->get(self::API_BASE."/user/installations/{$installationId}/repositories", [
                'per_page' => 100,
            ])
            ->throw()
            ->json('repositories', []);

        return array_map(fn (array $repository): array => [
            'id' => (int) $repository['id'],
            'full_name' => (string) $repository['full_name'],
            'default_branch' => (string) ($repository['default_branch'] ?? 'HEAD'),
        ], $repositories);
    }

    /**
     * Fetch the recursive file tree of a repository.
     *
     * @return array<int, array{path: string, type: string}>
     */
    public function repositoryTree(string $fullName, string $ref): array
    {
        $tree = $this->request()
            ->get(self::API_BASE."/repos/{$fullName}/git/trees/{$ref}", [
                'recursive' => 'true',
            ])
            ->throw()
            ->json('tree', []);

        return array_map(fn (array $entry): array => [
            'path' => (string) $entry['path'],
            'type' => (string) $entry['type'],
        ], $tree);
    }

    /**
     * Fetch the raw content of a single file.
     */
    public function fileContent(string $fullName, string $path, string $ref): ?string
    {
        try {
            return Http::accept('application/vnd.github.raw')
                ->get(self::API_BASE."/repos/{$fullName}/contents/{$path}", [
                    'ref' => $ref,
                ])
                ->throw()
                ->body();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Create a prepared GitHub API request with optional token authentication.
     */
    private function request(?string $token = null): PendingRequest
    {
        $request = Http::accept('application/vnd.github+json');

        if ($token !== null && $token !== '') {
            $request = $request->withToken($token);
        }

        return $request;
    }
}
