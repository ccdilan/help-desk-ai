<?php

namespace App\Services\Ai;

use App\Contracts\EmbeddingClient;
use Illuminate\Support\Facades\Log;

/**
 * Decorator that logs every embedding request and response.
 *
 * Input texts are logged in full; vectors are summarized by dimension
 * count and a small sample to keep the log readable.
 */
class LoggingEmbeddingClient implements EmbeddingClient
{
    public function __construct(private readonly EmbeddingClient $inner) {}

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
     * Generate embedding vectors for a batch of texts, logging the exchange.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedAll(array $texts): array
    {
        $logger = Log::channel('ai');

        $logger->info('Embedding requested', [
            'driver' => $this->inner::class,
            'texts' => $texts,
        ]);

        $vectors = $this->inner->embedAll($texts);

        $logger->info('Embedding received', [
            'driver' => $this->inner::class,
            'vectors' => array_map(
                fn (array $vector): array => $this->summarize($vector),
                $vectors,
            ),
        ]);

        return $vectors;
    }

    /**
     * Summarize a vector instead of dumping hundreds of floats.
     *
     * @param  list<float>  $vector
     * @return array{dimensions: int, sample: list<float>}
     */
    private function summarize(array $vector): array
    {
        return [
            'dimensions' => count($vector),
            'sample' => array_slice($vector, 0, 8),
        ];
    }
}
