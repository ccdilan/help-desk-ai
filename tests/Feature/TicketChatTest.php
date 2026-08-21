<?php

use App\Enums\TicketStatus;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;

test('an authenticated user can create a ticket from an initial prompt', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $response = $this->actingAs($user)
        ->postJson("/api/projects/{$project->id}/tickets", [
            'title' => 'Search filters',
            'initial_prompt' => 'Users should be able to filter search results by date.',
        ]);

    $response->assertCreated()
        ->assertJsonPath('title', 'Search filters')
        ->assertJsonPath('status', TicketStatus::Discovering->value);

    $ticket = Ticket::query()->sole();

    expect($ticket->project_id)->toBe($project->id)
        ->and($ticket->status)->toBe(TicketStatus::Discovering)
        ->and($ticket->messages()->where('role', 'user')->count())->toBe(1)
        ->and($ticket->messages()->first()->content)->toBe('Users should be able to filter search results by date.');
});

test('ticket creation requires a title and an initial prompt', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->postJson("/api/projects/{$project->id}/tickets", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'initial_prompt']);

    expect(Ticket::count())->toBe(0);
});

test('a ticket can be fetched with its full message history in order', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->has(TicketMessage::factory()->count(3)->sequence(
        ['role' => 'user', 'content' => 'first'],
        ['role' => 'assistant', 'content' => 'second'],
        ['role' => 'user', 'content' => 'third'],
    ), 'messages')->create();

    $this->actingAs($user)
        ->getJson("/api/tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonPath('messages.0.content', 'first')
        ->assertJsonPath('messages.1.role', 'assistant')
        ->assertJsonPath('messages.2.content', 'third');
});

test('a user message can be appended to an existing conversation', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $response = $this->actingAs($user)
        ->postJson("/api/tickets/{$ticket->id}/messages", [
            'content' => 'It should also support exporting to PDF.',
        ]);

    $response->assertOk()
        ->assertJsonPath('messages.0.role', 'user')
        ->assertJsonPath('messages.0.content', 'It should also support exporting to PDF.');

    expect($ticket->messages()->count())->toBe(1);
});

test('appending a message requires content', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($user)
        ->postJson("/api/tickets/{$ticket->id}/messages", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);
});

test('guests cannot access the ticket chat api', function () {
    $project = Project::factory()->create();

    $this->getJson("/api/projects/{$project->id}/tickets")->assertUnauthorized();
    $this->postJson("/api/projects/{$project->id}/tickets", [])->assertUnauthorized();
});
