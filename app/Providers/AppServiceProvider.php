<?php

namespace App\Providers;

use App\Contracts\ChatClient;
use App\Contracts\EmbeddingClient;
use App\Contracts\FileIngestor;
use App\Contracts\GitHubClient;
use App\Services\Ai\OfflineChatClient;
use App\Services\Ai\OfflineEmbeddingClient;
use App\Services\Ai\OpenAIChatClient;
use App\Services\Ai\OpenAIEmbeddingClient;
use App\Services\GitHub\ApiGitHubClient;
use App\Services\Ingestion\ChunkingFileIngestor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GitHubClient::class, ApiGitHubClient::class);
        $this->app->bind(FileIngestor::class, ChunkingFileIngestor::class);

        $this->app->bind(EmbeddingClient::class, function (): EmbeddingClient {
            return match (config('ai.default')) {
                'openai' => app(OpenAIEmbeddingClient::class),
                default => new OfflineEmbeddingClient(
                    (int) config('ai.drivers.offline.dimensions', 1536),
                ),
            };
        });

        $this->app->bind(ChatClient::class, function (): ChatClient {
            return match (config('ai.default')) {
                'openai' => app(OpenAIChatClient::class),
                default => app(OfflineChatClient::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
