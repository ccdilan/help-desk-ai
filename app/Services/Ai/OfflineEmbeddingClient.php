<?php

namespace App\Services\Ai;

use App\Contracts\EmbeddingClient;

/**
 * Deterministic, key-free embedding driver for local development and tests.
 *
 * Vectors are derived from repeated SHA-256 hashing of the input text, so the
 * same text always produces the same vector and no network calls are made.
 */
class OfflineEmbeddingClient implements EmbeddingClient
{
    public function __construct(private readonly int $dimensions = 1536) {}

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
        return array_map(fn (string $text): array => $this->vectorize($text), $texts);
    }

    /**
     * Build a deterministic pseudo-random unit vector from the text hash.
     *
     * @return list<float>
     */
    private function vectorize(string $text): array
    {
        $values = [];
        $seed = $text;

        while (count($values) < $this->dimensions) {
            $bytes = hash('sha256', $seed, true);
            $seed = $bytes;

            foreach (str_split($bytes, 2) as $pair) {
                if (count($values) >= $this->dimensions) {
                    break;
                }

                $unpacked = unpack('s', $pair);

                if ($unpacked === false) {
                    continue;
                }

                $value = $unpacked[1] / 32768.0;
                $values[] = round(max(-1.0, min(1.0, $value)), 6);
            }
        }

        return $values;
    }
}
