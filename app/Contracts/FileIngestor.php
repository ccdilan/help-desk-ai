<?php

namespace App\Contracts;

use App\Models\GithubInstallation;

interface FileIngestor
{
    /**
     * Fetch, chunk, vectorize and upsert a single repository file.
     */
    public function ingest(GithubInstallation $installation, string $path, string $ref): void;
}
