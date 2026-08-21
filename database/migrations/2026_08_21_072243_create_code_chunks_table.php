<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('code_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->unsignedInteger('chunk_index')->default(0);
            $table->text('code_content');
            $table->timestamps();

            $table->unique(['project_id', 'file_path', 'chunk_index']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE code_chunks ADD COLUMN embedding vector(1536)');

        DB::statement(
            'CREATE INDEX code_chunks_embedding_hnsw_index ON code_chunks USING hnsw (embedding vector_cosine_ops)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_chunks');
    }
};
