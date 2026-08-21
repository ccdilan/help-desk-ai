<?php

namespace App\Services;

use App\Contracts\ChatClient;
use App\Enums\TicketStatus;
use App\Models\Ticket;

class PrdGeneratorService
{
    public function __construct(private readonly ChatClient $completions) {}

    /**
     * Summarize the discovery conversation into a structured SRS/PRD.
     *
     * @return Ticket The refreshed ticket with the generated document.
     */
    public function generate(Ticket $ticket): Ticket
    {
        $ticket->forceFill([
            'generated_prd' => $this->completions->complete($this->prompt($ticket)),
            'status' => TicketStatus::Review,
        ])->save();

        return $ticket->refresh();
    }

    /**
     * Build the chat messages used to author the requirements document.
     *
     * @return list<array{role: string, content: string}>
     */
    private function prompt(Ticket $ticket): array
    {
        return [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $this->transcript($ticket)],
        ];
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            'You are a senior business analyst authoring a Software Requirements Specification (SRS/PRD).',
            'Summarize the discovery conversation into a structured requirements document in Markdown.',
            'Use these sections: Overview, Goals, Functional Requirements, Non-Functional Requirements, Out of Scope, Open Questions.',
            'Number each requirement (e.g. FR-1, NFR-1) and keep statements testable.',
            'Base the document strictly on the conversation; do not invent features.',
        ]);
    }

    /**
     * Render the stored conversation as a transcript for the model.
     */
    private function transcript(Ticket $ticket): string
    {
        $lines = [
            "Ticket title: {$ticket->title}",
            '',
        ];

        foreach ($ticket->messages()->orderBy('id')->get() as $message) {
            $speaker = $message->role === 'user' ? 'Requester' : 'Analyst';

            $lines[] = "{$speaker}: {$message->content}";
        }

        return implode("\n", $lines);
    }
}
