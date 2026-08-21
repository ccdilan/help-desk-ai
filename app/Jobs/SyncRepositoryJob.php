<?php

namespace App\Jobs;

use App\Models\GithubInstallation;
use App\Services\RepositorySyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncRepositoryJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public GithubInstallation $installation)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(RepositorySyncService $service): void
    {
        $service->sync($this->installation);
    }
}
