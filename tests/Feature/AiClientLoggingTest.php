<?php

use App\Services\Ai\LoggingChatClient;
use App\Services\Ai\LoggingEmbeddingClient;
use App\Services\Ai\OfflineChatClient;
use App\Services\Ai\OfflineEmbeddingClient;

test('chat completions log the full request and response', function () {
    $logger = fakeLogChannel('ai');

    $client = new LoggingChatClient(new OfflineChatClient);

    $reply = $client->complete([
        ['role' => 'system', 'content' => 'You are an analyst.'],
        ['role' => 'user', 'content' => 'How does search work?'],
    ]);

    expect($reply)->toBe('offline-reply: How does search work?')
        ->and($logger->entries())->toHaveCount(2);

    $requested = $logger->entries()[0];
    $received = $logger->entries()[1];

    expect($requested['message'])->toBe('Chat completion requested')
        ->and($requested['context']['driver'])->toBe(OfflineChatClient::class)
        ->and($requested['context']['messages'])->toBe([
            ['role' => 'system', 'content' => 'You are an analyst.'],
            ['role' => 'user', 'content' => 'How does search work?'],
        ])
        ->and($received['message'])->toBe('Chat completion received')
        ->and($received['context']['reply'])->toBe('offline-reply: How does search work?');
});

test('embedding calls log the input texts and a vector summary', function () {
    $logger = fakeLogChannel('ai');

    $client = new LoggingEmbeddingClient(new OfflineEmbeddingClient(4));

    $vectors = $client->embedAll(['first text', 'second text']);

    expect($vectors)->toHaveCount(2)
        ->and($vectors[0])->toHaveCount(4);

    $requested = $logger->entries()[0];
    $received = $logger->entries()[1];

    expect($requested['message'])->toBe('Embedding requested')
        ->and($requested['context']['texts'])->toBe(['first text', 'second text'])
        ->and($received['message'])->toBe('Embedding received')
        ->and($received['context']['vectors'])->toHaveCount(2)
        ->and($received['context']['vectors'][0]['dimensions'])->toBe(4)
        ->and($received['context']['vectors'][0]['sample'])->toHaveCount(4);
});
