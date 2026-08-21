<?php

namespace App\Services;

use App\Contracts\ChatClient;
use App\Contracts\EmbeddingClient;
use App\Models\Ticket;
use App\Repositories\Contracts\CodeChunkRepository as CodeChunkRepositoryContract;
use RuntimeException;

class DiscoveryAgentService
{
    /**
     * Maximum number of repository files referenced in the system prompt.
     */
    private const CONTEXT_LIMIT = 8;

    public function __construct(
        private readonly TicketChatService $chat,
        private readonly EmbeddingClient $embeddings,
        private readonly ChatClient $completions,
        private readonly CodeChunkRepositoryContract $codeChunks,
    ) {}

    /**
     * Store the user's message and generate the assistant's clarifying reply.
     *
     * @return Ticket The ticket with its full message history.
     */
    public function respond(Ticket $ticket, string $userMessage): Ticket
    {
        $this->chat->appendMessage($ticket, 'user', $userMessage);

        return $this->reply($ticket);
    }

    /**
     * Generate the assistant's next turn from the current conversation.
     *
     * @return Ticket The ticket with its full message history.
     */
    public function reply(Ticket $ticket): Ticket
    {
        $latestUser = $ticket->messages()->where('role', 'user')->latest('id')->first();

        $context = [];

        if ($latestUser !== null) {
            $context = $this->relevantContext($ticket, $latestUser->content);
        }

        $reply = $this->completions->complete([
            ['role' => 'system', 'content' => $this->systemPrompt($context)],
            ...$this->conversationHistory($ticket),
        ]);

        $this->chat->appendMessage($ticket, 'assistant', $reply);

        return $ticket->load('messages');
    }

    /**
     * Find repository files relevant to the user's message.
     *
     * When the ticket is scoped to a specific repository, only that
     * repository's indexed chunks are searched.
     *
     * Returns file paths only — raw source code is never exposed to the model.
     *
     * @return list<string>
     */
    private function relevantContext(Ticket $ticket, string $message): array
    {
        try {
            $results = $this->codeChunks->searchSimilar(
                $ticket->project_id,
                $this->embeddings->embed($message),
                self::CONTEXT_LIMIT,
                $ticket->github_installation_id,
            );
        } catch (RuntimeException) {
            // Vector search requires PostgreSQL; degrade gracefully without context.
            return [];
        }

        $paths = [];

        foreach ($results as $result) {
            if (! in_array($result['chunk']->file_path, $paths, true)) {
                $paths[] = $result['chunk']->file_path;
            }
        }

        return $paths;
    }

    /**
     * Build the system prompt that frames the discovery interview.
     *
     * @param  list<string>  $contextPaths
     */
    private function systemPrompt(array $contextPaths): string
    {
        $lines = [
            'You are a senior requirements analyst running a discovery interview for a new helpdesk ticket.',
            'Your goal is to understand the requester\'s needs by asking focused clarifying questions, one at a time.',
            'The team\'s codebase may already address part of the request; prefer asking about gaps rather than restating what exists.',
            'Never quote or reveal raw source code contents in your replies.',
            'Keep replies concise and conversational.',
        ];

        if ($contextPaths === []) {
            $lines[] = 'No indexed repository context is available yet for this project.';
        } else {
            $lines[] = 'Repository files most relevant to the discussion:';
            foreach ($contextPaths as $path) {
                $lines[] = "- {$path}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Convert the stored conversation into chat-client message format.
     *
     * @return list<array{role: string, content: string}>
     */
    private function conversationHistory(Ticket $ticket): array
    {
        $history = [];

        foreach ($ticket->messages()->orderBy('id')->get() as $message) {
            $history[] = ['role' => $message->role, 'content' => $message->content];
        }

        return $history;
    }
}
