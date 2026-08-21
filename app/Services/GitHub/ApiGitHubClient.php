<?php

namespace App\Services\GitHub;

use App\Contracts\GitHubClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
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
    public function repositoryTree(string $fullName, string $ref, ?int $installationId = null): array
    {
        $tree = $this->request($this->installationToken($installationId))
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
    public function fileContent(string $fullName, string $path, string $ref, ?int $installationId = null): ?string
    {
        try {
            $token = $this->installationToken($installationId);

            $request = Http::accept('application/vnd.github.raw');

            if ($token !== null && $token !== '') {
                $request = $request->withToken($token);
            }

            return $request
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
     * Mint (and cache) a GitHub App installation access token.
     *
     * Returns null when the app credentials are not configured, in which case
     * callers fall back to unauthenticated requests (public repositories only).
     */
    private function installationToken(?int $installationId): ?string
    {
        if ($installationId === null) {
            return null;
        }

        $appId = (string) config('services.github.app_id');
        $privateKey = (string) config('services.github.private_key');

        if ($privateKey !== '' && is_file($privateKey)) {
            $privateKey = (string) file_get_contents($privateKey);
        }

        if ($appId === '' || $privateKey === '') {
            return null;
        }

        return Cache::remember(
            "github.installation-token.{$installationId}",
            now()->addMinutes(50),
            fn (): string => $this->requestInstallationToken((int) $appId, $privateKey, $installationId),
        );
    }

    /**
     * Exchange a signed app JWT for an installation access token.
     */
    private function requestInstallationToken(int $appId, string $privateKey, int $installationId): string
    {
        return $this->request()
            ->withHeaders(['Authorization' => 'Bearer '.$this->appJwt($appId, $privateKey)])
            ->post(self::API_BASE."/app/installations/{$installationId}/access_tokens")
            ->throw()
            ->json('token', '');
    }

    /**
     * Build a short-lived RS256 JWT signed with the GitHub App private key.
     */
    private function appJwt(int $appId, string $privateKey): string
    {
        $encode = static fn (array $payload): string => rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');

        $header = $encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $claims = $encode([
            'iat' => time() - 60,
            'exp' => time() + 540,
            'iss' => $appId,
        ]);

        $signature = '';
        openssl_sign("{$header}.{$claims}", $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return "{$header}.{$claims}.".rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
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
