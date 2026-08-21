<?php

namespace App\Contracts;

interface ChatClient
{
    /**
     * Complete a chat conversation and return the assistant message content.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function complete(array $messages): string;
}
