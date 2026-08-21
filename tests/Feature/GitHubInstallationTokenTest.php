<?php

use App\Contracts\GitHubClient;
use App\Jobs\SyncRepositoryJob;
use App\Models\GithubInstallation;
use App\Services\RepositorySyncService;
use Illuminate\Support\Facades\Http;

function testRsaPrivateKey(): string
{
    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($key, $pem);

    return $pem;
}

function fakePrivateRepoSync(): void
{
    Http::fake([
        'https://api.github.com/app/installations/*/access_tokens' => Http::response([
            'token' => 'installation-token-abc',
            'expires_at' => now()->addHour()->toIso8601String(),
        ]),
        'https://api.github.com/repos/*/git/trees/*' => Http::response([
            'tree' => [
                ['path' => 'app/Models/User.php', 'type' => 'blob'],
            ],
        ]),
        'https://api.github.com/repos/*/contents/*' => Http::response("<?php\n\nclass A\n{\n}\n"),
    ]);
}

test('repositoryTree authenticates with an installation access token when the app is configured', function () {
    Cache::flush();
    config([
        'services.github.app_id' => '12345',
        'services.github.private_key' => testRsaPrivateKey(),
    ]);

    Http::fake([
        'https://api.github.com/app/installations/42/access_tokens' => Http::response([
            'token' => 'installation-token-abc',
            'expires_at' => now()->addHour()->toIso8601String(),
        ]),
        'https://api.github.com/repos/acme/widgets/git/trees/*' => Http::response(['tree' => []]),
    ]);

    app(GitHubClient::class)->repositoryTree('acme/widgets', 'HEAD', 42);

    Http::assertSent(function ($request): bool {
        if (str_contains($request->url(), '/access_tokens')) {
            $jwt = substr((string) $request->header('Authorization')[0], strlen('Bearer '));

            expect(substr_count($jwt, '.'))->toBe(2);

            return true;
        }

        if (str_contains($request->url(), '/git/trees/')) {
            expect($request->header('Authorization')[0])->toBe('Bearer installation-token-abc');

            return true;
        }

        return false;
    });
});

test('sync of a private repository uses the installation token for tree and file requests', function () {
    Cache::flush();
    config([
        'services.github.app_id' => '12345',
        'services.github.private_key' => testRsaPrivateKey(),
    ]);

    fakePrivateRepoSync();

    $installation = GithubInstallation::factory()->create(['status' => 'pending']);

    (new SyncRepositoryJob($installation))->handle(app(RepositorySyncService::class));

    expect($installation->refresh()->status)->toBe('synced');

    Http::assertSent(function ($request): bool {
        if (str_contains($request->url(), '/access_tokens')) {
            return true;
        }

        return str_contains($request->url(), '/repos/')
            ? $request->header('Authorization')[0] === 'Bearer installation-token-abc'
            : false;
    });

    Http::assertSentCount(3);
});

test('requests fall back to anonymous when the app credentials are not configured', function () {
    Cache::flush();
    config([
        'services.github.app_id' => null,
        'services.github.private_key' => null,
    ]);

    Http::fake([
        'https://api.github.com/repos/acme/widgets/git/trees/*' => Http::response(['tree' => []]),
    ]);

    app(GitHubClient::class)->repositoryTree('acme/widgets', 'HEAD', 42);

    Http::assertSent(function ($request): bool {
        if (str_contains($request->url(), '/access_tokens')) {
            return false;
        }

        return str_contains($request->url(), '/git/trees/')
            && $request->header('Authorization') === [];
    });
});
