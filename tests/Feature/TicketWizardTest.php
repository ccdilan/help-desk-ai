<?php

use App\Models\GithubInstallation;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the ticket wizard renders with selectable projects and their repositories', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $project = Project::factory()->create(['name' => 'Alpha Platform']);
    $installation = GithubInstallation::factory()->for($project)->create([
        'repository_full_name' => 'cyntreksl/jobbazaar-backoffice',
    ]);

    $this->actingAs($user)
        ->get(route('tickets.create', $team->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Create')
            ->has('projects', 1)
            ->where('projects.0.id', $project->id)
            ->where('projects.0.name', 'Alpha Platform')
            ->has('projects.0.installations', 1)
            ->where('projects.0.installations.0.id', $installation->id)
            ->where('projects.0.installations.0.repositoryFullName', 'cyntreksl/jobbazaar-backoffice')
        );
});

test('guests cannot open the ticket wizard', function () {
    $this->get('/some-team/tickets/create')->assertRedirect();
});
