<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the srs review page renders the initial prompt beside the generated prd', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $ticket = Ticket::factory()->reviewed()->create([
        'title' => 'Bulk export',
        'initial_prompt' => 'We need to export tickets in bulk.',
        'generated_prd' => "# SRS\n\nFR-1: Export to CSV.",
    ]);
    $ticket->messages()->createMany([
        ['role' => 'user', 'content' => 'I need bulk export.'],
        ['role' => 'assistant', 'content' => 'Which formats do you need?'],
    ]);

    $this->actingAs($user)
        ->get(route('tickets.show', [$team->slug, $ticket]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Show')
            ->where('ticket.title', 'Bulk export')
            ->where('ticket.initial_prompt', 'We need to export tickets in bulk.')
            ->where('ticket.status', TicketStatus::Review->value)
            ->where('ticket.generated_prd', "# SRS\n\nFR-1: Export to CSV.")
            ->where('ticket.messages.0.role', 'user')
            ->where('ticket.messages.0.content', 'I need bulk export.')
            ->where('ticket.messages.1.role', 'assistant')
        );
});

test('the srs review page shows an empty transcript when no messages exist', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $ticket = Ticket::factory()->reviewed()->create();

    $this->actingAs($user)
        ->get(route('tickets.show', [$team->slug, $ticket]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Show')
            ->has('ticket.messages', 0)
        );
});

test('a ticket can be signed off after review', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->reviewed()->create();

    $this->actingAs($user)
        ->postJson("/api/tickets/{$ticket->id}/sign-off")
        ->assertOk()
        ->assertJsonPath('status', TicketStatus::SignedOff->value);

    expect($ticket->refresh()->status)->toBe(TicketStatus::SignedOff);
});

test('guests cannot open the srs review page', function () {
    $this->get('/some-team/tickets/1')->assertRedirect();
});
