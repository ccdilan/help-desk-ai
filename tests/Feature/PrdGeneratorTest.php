<?php

use App\Contracts\ChatClient;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;

class PrdRecordingChatSpy implements ChatClient
{
    public array $calls = [];

    /**
     * Record the conversation and reply with a canned SRS document.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function complete(array $messages): string
    {
        $this->calls[] = $messages;

        return "# Software Requirements Specification\n\n## FR-1\nBulk export to CSV.";
    }
}

test('the prd endpoint summarizes the conversation into a reviewable document', function () {
    $spy = new PrdRecordingChatSpy;
    $this->app->instance(ChatClient::class, $spy);

    $user = User::factory()->create();
    $ticket = Ticket::factory()->has(TicketMessage::factory()->count(2)->sequence(
        ['role' => 'user', 'content' => 'I need bulk export of tickets.'],
        ['role' => 'assistant', 'content' => 'Which formats are required?'],
    ), 'messages')->create();

    $response = $this->actingAs($user)
        ->postJson("/api/tickets/{$ticket->id}/prd")
        ->assertOk()
        ->assertJsonPath('status', TicketStatus::Review->value)
        ->assertJsonPath('generated_prd', "# Software Requirements Specification\n\n## FR-1\nBulk export to CSV.");

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Review)
        ->and($ticket->generated_prd)->toContain('FR-1');
});

test('the generator prompt contains the full conversation transcript', function () {
    $spy = new PrdRecordingChatSpy;
    $this->app->instance(ChatClient::class, $spy);

    $user = User::factory()->create();
    $ticket = Ticket::factory()->has(TicketMessage::factory()->count(2)->sequence(
        ['role' => 'user', 'content' => 'I need bulk export of tickets.'],
        ['role' => 'assistant', 'content' => 'Which formats are required?'],
    ), 'messages')->create();

    $this->actingAs($user)->postJson("/api/tickets/{$ticket->id}/prd")->assertOk();

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0][0]['role'])->toBe('system')
        ->and($spy->calls[0][0]['content'])->toContain('Software Requirements Specification')
        ->and($spy->calls[0][1]['content'])->toContain('Ticket title: '.$ticket->title)
        ->and($spy->calls[0][1]['content'])->toContain('Requester: I need bulk export of tickets.')
        ->and($spy->calls[0][1]['content'])->toContain('Analyst: Which formats are required?');
});
