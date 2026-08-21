<?php

use App\Contracts\FileIngestor;
use App\Jobs\SyncRepositoryJob;
use App\Models\CodeChunk;
use App\Models\GithubInstallation;
use App\Models\Project;
use App\Models\User;
use App\Services\RepositorySyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function fakeRepositoryTree(): void
{
    Http::fake([
        'https://api.github.com/repos/*/git/trees/*' => Http::response([
            'tree' => [
                ['path' => 'app/Models/User.php', 'type' => 'blob'],
                ['path' => 'resources/js/app.ts', 'type' => 'blob'],
                ['path' => 'resources/js/pages/Dashboard.vue', 'type' => 'blob'],
                ['path' => 'public/logo.png', 'type' => 'blob'],
                ['path' => 'vendor/autoload.php', 'type' => 'blob'],
                ['path' => 'node_modules/vue/index.js', 'type' => 'blob'],
                ['path' => 'readme.md', 'type' => 'blob'],
            ],
        ]),
    ]);
}

test('sync ingests supported files and marks the installation synced', function () {
    fakeRepositoryTree();

    $spy = new class implements FileIngestor
    {
        public array $ingested = [];

        public function ingest(GithubInstallation $installation, string $path, string $ref): void
        {
            $this->ingested[] = $path;
        }
    };
    $this->app->instance(FileIngestor::class, $spy);

    $installation = GithubInstallation::factory()->create(['status' => 'pending']);

    (new SyncRepositoryJob($installation))->handle(app(RepositorySyncService::class));

    expect($spy->ingested)->toBe([
        'app/Models/User.php',
        'resources/js/app.ts',
        'resources/js/pages/Dashboard.vue',
    ])
        ->and($installation->refresh()->status)->toBe('synced')
        ->and($installation->last_synced_at)->not->toBeNull();
});

test('sync removes chunks of files that no longer exist in the tree', function () {
    fakeRepositoryTree();

    $installation = GithubInstallation::factory()->create();
    $staleChunk = CodeChunk::factory()->for($installation, 'installation')->create([
        'file_path' => 'app/Models/Removed.php',
    ]);
    $keptChunk = CodeChunk::factory()->for($installation, 'installation')->create([
        'file_path' => 'app/Models/User.php',
    ]);

    (new SyncRepositoryJob($installation))->handle(app(RepositorySyncService::class));

    expect(CodeChunk::find($staleChunk->id))->toBeNull()
        ->and(CodeChunk::find($keptChunk->id))->not->toBeNull();
});

test('sync marks the installation as failed when the tree cannot be fetched', function () {
    Http::fake([
        'https://api.github.com/repos/*/git/trees/*' => Http::response('oops', 500),
    ]);

    $installation = GithubInstallation::factory()->create();

    (new SyncRepositoryJob($installation))->handle(app(RepositorySyncService::class));

    expect($installation->refresh()->status)->toBe('failed');
});

test('successful connection dispatches a sync job per repository', function () {
    Queue::fake();

    Http::fake([
        'https://github.com/login/oauth/access_token' => Http::response(['access_token' => 'user-token']),
        'https://api.github.com/user/installations/123/repositories*' => Http::response([
            'repositories' => [
                ['id' => 1001, 'full_name' => 'cyntrek/api-service', 'default_branch' => 'main'],
                ['id' => 1002, 'full_name' => 'cyntrek/web-app', 'default_branch' => 'main'],
            ],
        ]),
    ]);

    $user = User::factory()->create();
    $project = Project::factory()->create();
    Session::put('github.oauth.state', 'valid-state');
    Session::put('github.oauth.project_id', $project->id);

    $this->actingAs($user)
        ->get(route('github.callback', [
            'code' => 'abc',
            'installation_id' => 123,
            'state' => 'valid-state',
        ]))
        ->assertRedirect(route('dashboard'));

    Queue::assertPushed(SyncRepositoryJob::class, 2);
});
