<?php

namespace App\Services;

use App\Contracts\ChatClient;
use App\Contracts\EmbeddingClient;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Repositories\Contracts\CodeChunkRepository as CodeChunkRepositoryContract;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DiscoveryAgentService
{
    /**
     * Maximum number of repository files referenced in the system prompt.
     */
    private const CONTEXT_LIMIT = 8;

    /**
     * Maximum number of clarifying questions asked before wrapping up.
     */
    public const MAX_QUESTIONS = 3;

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
     * Once the question limit is reached the assistant stops asking
     * questions and replies with a closing requirements summary.
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

        $questionsAsked = $ticket->messages()->where('role', 'assistant')->count();

        $systemPrompt = $questionsAsked >= self::MAX_QUESTIONS
            ? $this->wrapUpPrompt($context)
            : $this->systemPrompt($context);

        $reply = $this->completions->complete([
            ['role' => 'system', 'content' => $systemPrompt],
            ...$this->conversationHistory($ticket),
        ]);

        $this->chat->appendMessage($ticket, 'assistant', $reply);

        return $ticket->load('messages');
    }

    /**
     * Determine whether discovery has wrapped up for the given messages.
     *
     * @param  iterable<TicketMessage>  $messages
     */
    public static function isDiscoveryComplete(iterable $messages): bool
    {
        $assistantReplies = 0;

        foreach ($messages as $message) {
            if ($message->role === 'assistant') {
                $assistantReplies++;
            }
        }

        return $assistantReplies > self::MAX_QUESTIONS;
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
            Log::channel('rag')->warning('Vector search unavailable, continuing without context', [
                'ticket_id' => $ticket->id,
                'project_id' => $ticket->project_id,
                'installation_id' => $ticket->github_installation_id,
            ]);

            return [];
        }

        $paths = [];

        foreach ($results as $result) {
            if (! in_array($result['chunk']->file_path, $paths, true)) {
                $paths[] = $result['chunk']->file_path;
            }
        }

        Log::channel('rag')->info('Discovery context selected', [
            'ticket_id' => $ticket->id,
            'project_id' => $ticket->project_id,
            'installation_id' => $ticket->github_installation_id,
            'message' => $message,
            'context_paths' => $paths,
        ]);

        return $paths;
    }

    /**
     * Build the system prompt that frames the discovery interview.
     *
     * @param  list<string>  $contextPaths
     */
    private function systemPrompt(array $contextPaths): string
    {
        return implode("\n", [
            'You are a senior requirements analyst running a discovery interview for a new helpdesk ticket.',
            'Your goal is to understand the requester\'s needs by asking focused clarifying questions, one at a time.',
            'Ask at most '.self::MAX_QUESTIONS.' clarifying questions in total; once you have enough information, stop asking.',
            "The team's codebase may already address part of the request; prefer asking about gaps rather than restating what exists.",
            'Never quote or reveal raw source code contents in your replies.',
            ...$this->contextLines($contextPaths),
        ]);
    }

    /**
     * Build the system prompt used once the question limit is reached.
     *
     * The assistant must close the interview with a requirements summary
     * instead of asking further questions.
     *
     * @param  list<string>  $contextPaths
     */
    private function wrapUpPrompt(array $contextPaths): string
    {
        return implode("\n", [
            'You are a senior requirements analyst closing a discovery interview for a new helpdesk ticket.',
            'The limit of '.self::MAX_QUESTIONS.' clarifying questions has been reached.',
            'Do not ask any further questions.',
            'Reply with a short bullet list summarizing the confirmed requirements based strictly on the conversation.',
            'End by inviting the requester to press "Generate SRS & submit" to finalize the ticket.',
            'Never quote or reveal raw source code contents in your replies.',
            ...$this->contextLines($contextPaths),
        ]);
    }

    /**
     * Describe the indexed repository context available to the model.
     *
     * @param  list<string>  $contextPaths
     * @return list<string>
     */
    private function contextLines(array $contextPaths): array
    {
        if ($contextPaths === []) {
            return ['No indexed repository context is available yet for this project.'];
        }

        $lines = ['Repository files most relevant to the discussion:'];

        foreach ($contextPaths as $path) {
            $lines[] = "- {$path}";
        }

        return $lines;
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
