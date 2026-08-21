<?php

use App\Models\CodeChunk;
use App\Models\GithubInstallation;
use App\Models\Project;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('projects table is created with expected columns', function () {
    expect(Schema::hasTable('projects'))->toBeTrue()
        ->and(Schema::hasColumns('projects', ['id', 'name', 'slug', 'description', 'created_at', 'updated_at']))->toBeTrue();
});

test('github_installations table is created with expected columns', function () {
    expect(Schema::hasTable('github_installations'))->toBeTrue()
        ->and(Schema::hasColumns('github_installations', [
            'id', 'project_id', 'installation_id', 'repository_id',
            'repository_full_name', 'status', 'last_synced_at', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

test('code_chunks table is created with expected columns', function () {
    expect(Schema::hasTable('code_chunks'))->toBeTrue()
        ->and(Schema::hasColumns('code_chunks', [
            'id', 'project_id', 'file_path', 'chunk_index', 'code_content', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

test('deleting a project cascades to its installations and chunks', function () {
    $project = Project::factory()->create();
    $installation = GithubInstallation::factory()->for($project)->create();
    $chunk = CodeChunk::factory()->for($project)->create();

    $project->delete();

    expect(GithubInstallation::find($installation->id))->toBeNull()
        ->and(CodeChunk::find($chunk->id))->toBeNull();
});

test('code chunks are unique per project, file path and chunk index', function () {
    $project = Project::factory()->create();
    CodeChunk::factory()->for($project)->create(['file_path' => 'app/Models/User.php', 'chunk_index' => 0]);

    CodeChunk::factory()->for($project)->create(['file_path' => 'app/Models/User.php', 'chunk_index' => 1]);

    expect(fn () => CodeChunk::factory()->for($project)->create([
        'file_path' => 'app/Models/User.php',
        'chunk_index' => 0,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('pgvector extension and embedding column exist on pgsql', function () {
    $extension = DB::selectOne(
        'SELECT extname FROM pg_extension WHERE extname = ?',
        ['vector']
    );

    $column = DB::selectOne(
        "SELECT udt_name FROM information_schema.columns WHERE table_name = ? AND column_name = 'embedding'",
        ['code_chunks']
    );

    expect($extension?->extname)->toBe('vector')
        ->and($column?->udt_name)->toBe('vector');
})->skip(fn () => config('database.default') !== 'pgsql');

test('embedding column uses an HNSW index with cosine distance on pgsql', function () {
    $index = DB::selectOne(
        "SELECT indexdef FROM pg_indexes WHERE tablename = 'code_chunks' AND indexname = 'code_chunks_embedding_hnsw_index'"
    );

    expect($index?->indexdef)->toContain('USING hnsw')
        ->and($index?->indexdef)->toContain('vector_cosine_ops');
})->skip(fn () => config('database.default') !== 'pgsql');

test('cosine similarity search returns the nearest code chunk on pgsql', function () {
    $embedding = fn (int $hot): string => '['.implode(',', array_replace(array_fill(0, 1536, '0'), [$hot => '1'])).']';

    $project = Project::factory()->create();
    $near = CodeChunk::factory()->for($project)->create();
    $far = CodeChunk::factory()->for($project)->create();

    DB::table('code_chunks')->where('id', $near->id)->update([
        'embedding' => DB::raw("'{$embedding(0)}'::vector"),
    ]);
    DB::table('code_chunks')->where('id', $far->id)->update([
        'embedding' => DB::raw("'{$embedding(1)}'::vector"),
    ]);

    $nearest = DB::selectOne(
        'SELECT id FROM code_chunks ORDER BY embedding <=> ?::vector LIMIT 1',
        [$embedding(0)]
    );

    expect((int) $nearest->id)->toBe($near->id);
})->skip(fn () => config('database.default') !== 'pgsql');
