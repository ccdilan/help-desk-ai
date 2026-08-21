<?php

use App\Models\CodeChunk;
use App\Models\GithubInstallation;
use App\Repositories\Contracts\CodeChunkRepository;
use App\Repositories\Eloquent\CodeChunkRepository as EloquentCodeChunkRepository;
use Illuminate\Support\Facades\DB;

function seedEmbedding(CodeChunk $chunk, int $hot): void
{
    $vector = '['.implode(',', array_replace(array_fill(0, 1536, '0'), [$hot => '1'])).']';

    DB::table('code_chunks')->where('id', $chunk->id)->update([
        'embedding' => DB::raw("'{$vector}'::vector"),
    ]);
}

test('searchSimilar returns chunks ordered by cosine distance', function () {
    $installation = GithubInstallation::factory()->create();
    $otherProject = GithubInstallation::factory()->create();

    $target = CodeChunk::factory()->for($installation, 'installation')->create(['file_path' => 'app/Target.php']);
    $near = CodeChunk::factory()->for($installation, 'installation')->create(['file_path' => 'app/Near.php']);
    $far = CodeChunk::factory()->for($installation, 'installation')->create(['file_path' => 'app/Far.php']);
    $foreign = CodeChunk::factory()->for($otherProject, 'installation')->create(['file_path' => 'app/Foreign.php']);

    seedEmbedding($target, 10);
    seedEmbedding($near, 11);
    seedEmbedding($far, 500);
    seedEmbedding($foreign, 10);

    $query = array_fill(0, 1536, 0.0);
    $query[10] = 1.0;

    $results = app(CodeChunkRepository::class)->searchSimilar($installation->project_id, $query, 3);

    expect($results)->toHaveCount(3)
        ->and($results[0]['chunk']->is($target))->toBeTrue()
        ->and($results[0]['distance'])->toBeLessThan(0.01)
        ->and($results[1]['chunk']->is($near))->toBeTrue()
        ->and($results[2]['chunk']->is($far))->toBeTrue()
        ->and(collect($results)->pluck('chunk.id'))->not->toContain($foreign->id);
})->skip(fn () => config('database.default') !== 'pgsql');

test('searchSimilar respects the limit', function () {
    $installation = GithubInstallation::factory()->create();

    foreach (range(1, 4) as $i) {
        $chunk = CodeChunk::factory()->for($installation, 'installation')->create(['file_path' => "app/F{$i}.php"]);
        seedEmbedding($chunk, $i);
    }

    $query = array_fill(0, 1536, 0.0);
    $query[1] = 1.0;

    $results = app(CodeChunkRepository::class)->searchSimilar($installation->project_id, $query, 2);

    expect($results)->toHaveCount(2);
})->skip(fn () => config('database.default') !== 'pgsql');

test('the container binds the eloquent repository implementation', function () {
    expect(app(CodeChunkRepository::class))->toBeInstanceOf(EloquentCodeChunkRepository::class);
});
