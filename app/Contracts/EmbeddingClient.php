<?php

namespace App\Contracts;

interface EmbeddingClient
{
    /**
     * Generate an embedding vector for a single text.
     *
     * @return list<float>
     */
    public function embed(string $text): array;

    /**
     * Generate embedding vectors for a batch of texts.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedAll(array $texts): array;
}
