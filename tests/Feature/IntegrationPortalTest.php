<?php

use App\Jobs\SyncRepositoryJob;
use App\Models\GithubInstallation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the integration portal renders with projects and sync status', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam ?? null;

    if ($team === null) {
        $this->fail('User factory should provide a current team.');
    }

    $project = Project::factory()->create(['name' => 'Alpha Platform']);
    $synced = GithubInstallation::factory()->for($project)->create([
        'repository_full_name' => 'acme/alpha-api',
        'status' => 'synced',
        'last_synced_at' => now(),
    ]);
    GithubInstallation::factory()->for($project)->create([
        'repository_full_name' => 'acme/alpha-web',
        'status' => 'failed',
    ]);

    $response = $this->actingAs($user)
        ->get(route('integrations', $team->slug));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Integrations')
            ->has('projects', 1)
            ->where('projects.0.name', 'Alpha Platform')
            ->has('projects.0.installations', 2)
            ->where('projects.0.installations.0.repositoryFullName', 'acme/alpha-api')
            ->where('projects.0.installations.0.status', 'synced')
            ->where('projects.0.installations.1.status', 'failed')
        );

    expect($synced->exists)->toBeTrue();
});

test('guests cannot open the integration portal', function () {
    $this->get('/some-team/integrations')->assertRedirect();
});

test('a repository re-sync can be queued from the portal', function () {
    Queue::fake();

    $user = User::factory()->create();
    $installation = GithubInstallation::factory()->create();

    $this->actingAs($user)
        ->postJson("/api/installations/{$installation->id}/sync")
        ->assertAccepted()
        ->assertJsonPath('status', 'syncing');

    Queue::assertPushed(SyncRepositoryJob::class, fn (SyncRepositoryJob $job): bool => $job->installation->is($installation));
});
