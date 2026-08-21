<?php

namespace App\Jobs;

use App\Services\GitHub\GitHubWebhookHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class HandleGitHubWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $event,
        public readonly array $payload,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GitHubWebhookHandler $handler): void
    {
        $handler->handle($this->event, $this->payload);
    }
}
