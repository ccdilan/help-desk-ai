<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Driver Configuration
    |--------------------------------------------------------------------------
    |
    | The AI layer is provider agnostic: services depend on the EmbeddingClient
    | and ChatClient contracts, while the active driver is selected here.
    | Switching providers (e.g. to an OpenAI-compatible endpoint) requires
    | only an environment change - never a code change.
    |
    */

    'default' => env('AI_DRIVER', 'offline'),

    'drivers' => [

        'openai' => [
            'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
            'api_key' => env('AI_API_KEY'),
            'embedding_model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'chat_model' => env('AI_CHAT_MODEL', 'gpt-4o-mini'),
        ],

        'offline' => [
            'dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 1536),
        ],

    ],

];
