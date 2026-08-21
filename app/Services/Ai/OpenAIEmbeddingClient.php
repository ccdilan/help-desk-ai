<?php

namespace App\Services\Ai;

use App\Contracts\EmbeddingClient;
use Illuminate\Support\Facades\Http;

class OpenAIEmbeddingClient implements EmbeddingClient
{
    /**
     * Generate an embedding vector for a single text.
     *
     * @return list<float>
     */
    public function embed(string $text): array
    {
        return $this->embedAll([$text])[0];
    }

    /**
     * Generate embedding vectors for a batch of texts.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedAll(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $response = Http::withToken((string) config('ai.drivers.openai.api_key'))
            ->baseUrl((string) config('ai.drivers.openai.base_url'))
            ->post('/embeddings', [
                'model' => (string) config('ai.drivers.openai.embedding_model'),
                'input' => $texts,
            ])
            ->throw()
            ->json('data', []);

        usort($response, fn (array $a, array $b): int => $a['index'] <=> $b['index']);

        return array_map(
            fn (array $entry): array => array_values(array_map(
                fn (mixed $dimension): float => (float) $dimension,
                $entry['embedding'],
            )),
            $response,
        );
    }
}
