<?php

use App\Models\GithubInstallation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

test('guests are redirected to login when connecting github', function () {
    $project = Project::factory()->create();

    $this->get(route('github.connect', ['project' => $project->id]))
        ->assertRedirect(route('login'));
});

test('connect redirects to the github app installation page and stores state', function () {
    config(['services.github.app_slug' => 'cyntrek-ai']);

    $user = User::factory()->create();
    $project = Project::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('github.connect', ['project' => $project->id]));

    $response->assertRedirect();

    expect($response->headers->get('Location'))->toStartWith('https://github.com/apps/cyntrek-ai/installations/new?state=');

    $state = substr((string) $response->headers->get('Location'), -32);

    expect(Session::get('github.oauth.state'))->toBe($state)
        ->and(Session::get('github.oauth.project_id'))->toBe($project->id);
});

test('callback rejects a mismatched oauth state', function () {
    $user = User::factory()->create();
    Session::put('github.oauth.state', 'expected-state');

    $this->actingAs($user)
        ->get(route('github.callback', [
            'code' => 'abc',
            'installation_id' => 123,
            'state' => 'forged-state',
        ]))
        ->assertStatus(419);
});

test('callback stores installation repositories on the project', function () {
    Http::fake([
        'https://github.com/login/oauth/access_token' => Http::response(['access_token' => 'user-token']),
        'https://api.github.com/user/installations/123/repositories*' => Http::response([
            'repositories' => [
                ['id' => 1001, 'full_name' => 'cyntrek/api-service', 'default_branch' => 'main'],
                ['id' => 1002, 'full_name' => 'cyntrek/web-app', 'default_branch' => 'develop'],
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

    expect(GithubInstallation::count())->toBe(2);

    $installation = GithubInstallation::query()
        ->where('repository_id', 1001)
        ->firstOrFail();

    expect($installation->project_id)->toBe($project->id)
        ->and($installation->installation_id)->toBe('123')
        ->and($installation->repository_full_name)->toBe('cyntrek/api-service')
        ->and($installation->status)->toBe('pending');
});

test('callback updates an existing installation instead of duplicating it', function () {
    Http::fake([
        'https://github.com/login/oauth/access_token' => Http::response(['access_token' => 'user-token']),
        'https://api.github.com/user/installations/123/repositories*' => Http::response([
            'repositories' => [
                ['id' => 1001, 'full_name' => 'cyntrek/api-service-renamed', 'default_branch' => 'main'],
            ],
        ]),
    ]);

    $user = User::factory()->create();
    $project = Project::factory()->create();
    $existing = GithubInstallation::factory()->for($project)->create([
        'installation_id' => '123',
        'repository_id' => 1001,
        'repository_full_name' => 'cyntrek/api-service',
    ]);

    Session::put('github.oauth.state', 'valid-state');
    Session::put('github.oauth.project_id', $project->id);

    $this->actingAs($user)
        ->get(route('github.callback', [
            'code' => 'abc',
            'installation_id' => 123,
            'state' => 'valid-state',
        ]))
        ->assertRedirect(route('dashboard'));

    expect(GithubInstallation::count())->toBe(1)
        ->and($existing->refresh()->repository_full_name)->toBe('cyntrek/api-service-renamed');
});
