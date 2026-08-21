<?php

namespace App\Services\Ingestion;

use App\Contracts\FileIngestor;
use App\Models\GithubInstallation;

/**
 * No-op ingestor used until the chunking/vectorization pipeline is enabled.
 */
class NullFileIngestor implements FileIngestor
{
    /**
     * Intentionally does nothing.
     */
    public function ingest(GithubInstallation $installation, string $path, string $ref): void
    {
        //
    }
}
