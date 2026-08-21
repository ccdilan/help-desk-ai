<?php

namespace App\Services\Ai;

use App\Contracts\ChatClient;
use Illuminate\Support\Str;

/**
 * Deterministic, key-free chat driver for local development and tests.
 */
class OfflineChatClient implements ChatClient
{
    /**
     * Reply with a deterministic acknowledgement of the last user message.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function complete(array $messages): string
    {
        $lastUser = collect($messages)->reverse()->first(
            fn (array $message): bool => $message['role'] === 'user',
        );

        return 'offline-reply: '.Str::limit((string) ($lastUser['content'] ?? ''), 120);
    }
}
