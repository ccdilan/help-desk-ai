<?php

use App\Contracts\FileIngestor;
use App\Jobs\HandleGitHubWebhook;
use App\Models\CodeChunk;
use App\Models\GithubInstallation;
use App\Services\GitHub\GitHubWebhookHandler;
use App\Services\RepositorySyncService;
use Illuminate\Support\Facades\Queue;

function webhookPayload(): array
{
    return [
        'repository' => ['full_name' => 'cyntrek/api-service'],
        'after' => 'abc123commit',
        'deleted' => false,
        'commits' => [
            [
                'added' => ['src/NewFile.php'],
                'modified' => ['app/Models/User.php'],
                'removed' => ['app/Models/Old.php'],
            ],
        ],
    ];
}

function signedWebhookBody(array $payload, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), $secret);
}

test('webhook rejects requests with an invalid signature', function () {
    config()->set('services.github.webhook_secret', 'whsec_test');

    $this->post('/webhooks/github', webhookPayload(), [
        'HTTP_X-GitHub-Event' => 'push',
        'HTTP_X-Hub-Signature-256' => 'sha256=invalid',
    ])->assertStatus(401);

    expect(Queue::size())->toBe(0);
});

test('webhook rejects requests when no secret is configured', function () {
    config()->set('services.github.webhook_secret', '');

    $this->post('/webhooks/github', webhookPayload(), [
        'HTTP_X-GitHub-Event' => 'push',
    ])->assertStatus(401);
});

test('webhook queues the handler job with a valid signature', function () {
    Queue::fake();
    config()->set('services.github.webhook_secret', 'whsec_test');

    $payload = webhookPayload();
    $signature = signedWebhookBody($payload, 'whsec_test');

    $this->postJson('/webhooks/github', $payload, [
        'X-GitHub-Event' => 'push',
        'X-Hub-Signature-256' => $signature,
    ])->assertOk();

    Queue::assertPushed(HandleGitHubWebhook::class, fn (HandleGitHubWebhook $job): bool => $job->event === 'push'
        && $job->payload['repository']['full_name'] === 'cyntrek/api-service');
});

test('push events re-ingest changed files and remove deleted files', function () {
    $installation = GithubInstallation::factory()->create([
        'repository_full_name' => 'cyntrek/api-service',
    ]);

    $removedChunk = CodeChunk::factory()->for($installation, 'installation')->create([
        'file_path' => 'app/Models/Old.php',
    ]);
    $keptChunk = CodeChunk::factory()->for($installation, 'installation')->create([
        'file_path' => 'app/Models/User.php',
    ]);

    $spy = new class implements FileIngestor
    {
        public array $ingested = [];

        public function ingest(GithubInstallation $installation, string $path, string $ref): void
        {
            $this->ingested[] = [$path, $ref];
        }
    };
    $this->app->instance(FileIngestor::class, $spy);

    app(GitHubWebhookHandler::class)->handle('push', webhookPayload());

    expect($spy->ingested)->toBe([
        ['src/NewFile.php', 'abc123commit'],
        ['app/Models/User.php', 'abc123commit'],
    ])
        ->and(CodeChunk::find($removedChunk->id))->toBeNull()
        ->and(CodeChunk::find($keptChunk->id))->not->toBeNull()
        ->and($installation->refresh()->status)->toBe('synced');
});

test('merged pull requests trigger a full repository re-sync', function () {
    $installation = GithubInstallation::factory()->create([
        'repository_full_name' => 'cyntrek/api-service',
    ]);

    $sync = mock(RepositorySyncService::class);
    $sync->shouldReceive('sync')->once()->withArgs(fn (GithubInstallation $arg): bool => $arg->is($installation));
    $this->app->instance(RepositorySyncService::class, $sync);

    app(GitHubWebhookHandler::class)->handle('pull_request', [
        'repository' => ['full_name' => 'cyntrek/api-service'],
        'action' => 'closed',
        'pull_request' => ['merged' => true],
    ]);
});

test('events for unknown repositories are ignored silently', function () {
    app(GitHubWebhookHandler::class)->handle('push', [
        'repository' => ['full_name' => 'unknown/repo'],
        'commits' => [],
    ]);

    expect(true)->toBeTrue();
});
