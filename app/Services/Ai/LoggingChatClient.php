<?php

namespace App\Services\Ai;

use App\Contracts\ChatClient;
use Illuminate\Support\Facades\Log;

/**
 * Decorator that logs every chat completion request and response.
 */
class LoggingChatClient implements ChatClient
{
    public function __construct(private readonly ChatClient $inner) {}

    /**
     * Complete a chat conversation, logging the full exchange.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function complete(array $messages): string
    {
        $logger = Log::channel('ai');

        $logger->info('Chat completion requested', [
            'driver' => $this->inner::class,
            'messages' => $messages,
        ]);

        $reply = $this->inner->complete($messages);

        $logger->info('Chat completion received', [
            'driver' => $this->inner::class,
            'reply' => $reply,
        ]);

        return $reply;
    }
}
