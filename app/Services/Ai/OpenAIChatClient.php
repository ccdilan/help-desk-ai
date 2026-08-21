<?php

namespace App\Services\Ai;

use App\Contracts\ChatClient;
use Illuminate\Support\Facades\Http;

class OpenAIChatClient implements ChatClient
{
    /**
     * Complete a chat conversation via any OpenAI-compatible endpoint.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function complete(array $messages): string
    {
        return Http::withToken((string) config('ai.drivers.openai.api_key'))
            ->baseUrl((string) config('ai.drivers.openai.base_url'))
            ->post('/chat/completions', [
                'model' => (string) config('ai.drivers.openai.chat_model'),
                'messages' => $messages,
            ])
            ->throw()
            ->json('choices.0.message.content', '');
    }
}
