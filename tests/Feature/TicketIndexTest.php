<?php

use App\Enums\TicketStatus;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the tickets page lists every ticket with its project and status', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $project = Project::factory()->create(['name' => 'Alpha Platform']);
    $discovering = Ticket::factory()->for($project)->create([
        'title' => 'Bulk export',
        'created_at' => now()->subDay(),
    ]);
    $review = Ticket::factory()->for($project)->reviewed()->create(['title' => 'SSO login']);

    $this->actingAs($user)
        ->get(route('tickets.index', $team->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Index')
            ->has('tickets', 2)
            ->where('tickets.0.title', 'SSO login')
            ->where('tickets.0.project_name', 'Alpha Platform')
            ->where('tickets.0.status', TicketStatus::Review->value)
            ->where('tickets.0.has_srs', true)
            ->where('tickets.1.title', 'Bulk export')
            ->where('tickets.1.status', TicketStatus::Discovering->value)
            ->where('tickets.1.has_srs', false)
        );
});

test('guests cannot open the tickets list', function () {
    $this->get('/some-team/tickets')->assertRedirect();
});
