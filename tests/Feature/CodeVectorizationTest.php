<?php

use App\Contracts\EmbeddingClient;
use App\Contracts\FileIngestor;
use App\Contracts\GitHubClient;
use App\Models\CodeChunk;
use App\Models\GithubInstallation;
use App\Services\Ai\CodeChunker;
use App\Services\Ai\OfflineEmbeddingClient;
use App\Services\Ai\OpenAIEmbeddingClient;
use App\Services\Ingestion\ChunkingFileIngestor;
use Illuminate\Support\Facades\Http;

test('offline embedding client produces deterministic 1536 dimension vectors', function () {
    $client = new OfflineEmbeddingClient(1536);

    $first = $client->embed('class UserController { }');
    $second = $client->embed('class UserController { }');
    $other = $client->embed('completely different text');

    expect(count($first))->toBe(1536)
        ->and($first)->toBe($second)
        ->and($first)->not->toBe($other)
        ->and($first)->each->toBeFloat();
});

test('openai embedding client posts batches and parses ordered vectors', function () {
    Http::fake([
        'https://api.openai.com/v1/embeddings' => Http::response([
            'data' => [
                ['index' => 1, 'embedding' => [0.4, 0.5, 0.6]],
                ['index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
            ],
        ]),
    ]);

    config()->set('ai.default', 'openai');
    config()->set('ai.drivers.openai.api_key', 'test-key');

    $vectors = app(OpenAIEmbeddingClient::class)->embedAll(['alpha', 'beta']);

    expect($vectors[0])->toBe([0.1, 0.2, 0.3])
        ->and($vectors[1])->toBe([0.4, 0.5, 0.6]);

    Http::assertSent(fn ($request): bool => $request['model'] === 'text-embedding-3-small'
        && $request['input'] === ['alpha', 'beta']);
});

test('chunker splits php classes into method level chunks', function () {
    $chunker = new CodeChunker;

    $source = <<<'PHP'
        <?php

        class OrderService
        {
            public function place(Order $order): void
            {
                // first method body with enough lines
                $a = 1;
                $b = 2;
                $c = 3;
                echo $a + $b + $c;
            }

            public function cancel(Order $order): void
            {
                // second method body
                $d = 4;
                $e = 5;
                echo $d + $e;
            }
        }
        PHP;

    $chunks = $chunker->chunk($source);

    expect(count($chunks))->toBeGreaterThanOrEqual(2)
        ->and(collect($chunks)->filter(fn (string $chunk): bool => str_contains($chunk, 'place'))->values())->toHaveCount(1)
        ->and(collect($chunks)->filter(fn (string $chunk): bool => str_contains($chunk, 'cancel'))->values())->toHaveCount(1);
});

test('chunker splits vue single file components into blocks', function () {
    $chunker = new CodeChunker;

    $source = "<script setup lang=\"ts\">\nconst counter = ref(0);\n\nfunction increment(): void {\n    counter.value++;\n}\n</script>\n\n<template>\n    <button @click=\"increment\">{{ counter }}</button>\n</template>";

    $chunks = $chunker->chunk($source);

    expect(count($chunks))->toBe(2)
        ->and($chunks[0])->toStartWith('<script')
        ->and($chunks[1])->toStartWith('<template');
});

test('chunker returns empty array for blank content', function () {
    expect((new CodeChunker)->chunk("   \n  \n"))->toBe([]);
});

test('ingestor chunks vectorizes and upserts a repository file', function () {
    $installation = GithubInstallation::factory()->create();

    $github = mock(GitHubClient::class);
    $github->shouldReceive('fileContent')->once()->andReturn("<?php\n\nclass A\n{\n    public function x(): void\n    {\n    }\n}\n");
    $this->app->instance(GitHubClient::class, $github);

    $ingestor = new ChunkingFileIngestor(
        $github,
        new CodeChunker,
        new OfflineEmbeddingClient(1536),
    );

    $ingestor->ingest($installation, 'app/A.php', 'HEAD');

    $chunks = CodeChunk::query()
        ->where('github_installation_id', $installation->id)
        ->where('file_path', 'app/A.php')
        ->get();

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]->project_id)->toBe($installation->project_id)
        ->and($chunks[0]->code_content)->toContain('function x')
        ->and(str_starts_with((string) $chunks[0]->getRawOriginal('embedding'), '['))->toBeTrue();
});

test('re-ingesting a file replaces its previous chunks', function () {
    $installation = GithubInstallation::factory()->create();

    $github = mock(GitHubClient::class);
    $github->shouldReceive('fileContent')->twice()->andReturnValues([
        "<?php\n\nclass A {}\n",
        "<?php\n\nclass B {}\n",
    ]);
    $this->app->instance(GitHubClient::class, $github);

    $ingestor = new ChunkingFileIngestor($github, new CodeChunker, new OfflineEmbeddingClient);

    $ingestor->ingest($installation, 'app/A.php', 'HEAD');
    $ingestor->ingest($installation, 'app/A.php', 'HEAD');

    $chunks = CodeChunk::query()
        ->where('github_installation_id', $installation->id)
        ->where('file_path', 'app/A.php')
        ->get();

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]->code_content)->toContain('class B');
});

test('ingestor skips files without content', function () {
    $installation = GithubInstallation::factory()->create();

    $github = mock(GitHubClient::class);
    $github->shouldReceive('fileContent')->once()->andReturn(null);
    $this->app->instance(GitHubClient::class, $github);

    $ingestor = new ChunkingFileIngestor($github, new CodeChunker, new OfflineEmbeddingClient);

    $ingestor->ingest($installation, 'app/empty.php', 'HEAD');

    expect(CodeChunk::count())->toBe(0);
});

test('the container binds the chunking ingestor and offline embedding driver by default', function () {
    expect(app(FileIngestor::class))->toBeInstanceOf(ChunkingFileIngestor::class)
        ->and(app(EmbeddingClient::class))->toBeInstanceOf(OfflineEmbeddingClient::class);
});
