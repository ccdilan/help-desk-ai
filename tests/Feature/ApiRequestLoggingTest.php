<?php

use App\Models\Ticket;
use App\Models\User;

test('every api request and its response are logged', function () {
    $logger = fakeLogChannel('api');

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($user)
        ->getJson("/api/tickets/{$ticket->id}")
        ->assertOk();

    expect($logger->entries())->toHaveCount(1);

    $entry = $logger->entries()[0];

    expect($entry['message'])->toBe('API request handled')
        ->and($entry['context']['method'])->toBe('GET')
        ->and($entry['context']['url'])->toContain("/api/tickets/{$ticket->id}")
        ->and($entry['context']['route'])->toBe('api.tickets.show')
        ->and($entry['context']['user_id'])->toBe($user->id)
        ->and($entry['context']['status'])->toBe(200)
        ->and($entry['context']['response']['id'])->toBe($ticket->id)
        ->and($entry['context']['duration_ms'])->toBeInt();
});

test('api request payloads are logged', function () {
    $logger = fakeLogChannel('api');

    $user = User::factory()->create();
    $ticket = Ticket::factory()->hasMessages(['role' => 'user', 'content' => 'I need bulk export.'])->create();

    $this->actingAs($user)
        ->postJson("/api/tickets/{$ticket->id}/messages", ['content' => 'Also CSV and PDF formats.'])
        ->assertOk();

    $entry = $logger->entries()[0];

    expect($entry['context']['method'])->toBe('POST')
        ->and($entry['context']['request'])->toBe(['content' => 'Also CSV and PDF formats.'])
        ->and($entry['context']['status'])->toBe(200)
        ->and($entry['context']['response']['id'])->toBe($ticket->id);
});

test('unauthenticated api requests are logged as well', function () {
    $logger = fakeLogChannel('api');

    $this->postJson('/api/projects/1/tickets', ['title' => 'x'])->assertUnauthorized();

    expect($logger->entries())->toHaveCount(1);

    $entry = $logger->entries()[0];

    expect($entry['message'])->toBe('API request handled')
        ->and($entry['context']['status'])->toBe(401)
        ->and($entry['context']['user_id'])->toBeNull()
        ->and($entry['context']['request'])->toBe(['title' => 'x']);
});
