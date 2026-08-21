<?php

namespace App\Services;

use App\Models\GithubInstallation;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketMessage;

class TicketChatService
{
    /**
     * Create a ticket from an initial prompt and store it as the first user message.
     *
     * When an installation is given, the ticket's discovery context is scoped
     * to that repository only.
     */
    public function startTicket(Project $project, string $title, string $initialPrompt, ?GithubInstallation $installation = null): Ticket
    {
        $ticket = $project->tickets()->create([
            'github_installation_id' => $installation?->id,
            'title' => $title,
            'initial_prompt' => $initialPrompt,
        ]);

        $this->appendMessage($ticket, 'user', $initialPrompt);

        return $ticket->load('messages');
    }

    /**
     * Append a message to the ticket's discovery conversation.
     */
    public function appendMessage(Ticket $ticket, string $role, string $content): TicketMessage
    {
        return $ticket->messages()->create([
            'role' => $role,
            'content' => $content,
        ]);
    }
}
