<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('code_chunks', function (Blueprint $table) {
            $table->foreignId('github_installation_id')->nullable()->after('project_id')->constrained()->cascadeOnDelete();

            $table->dropUnique(['project_id', 'file_path', 'chunk_index']);
            $table->unique(['github_installation_id', 'file_path', 'chunk_index']);

            $table->foreignId('project_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('code_chunks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('github_installation_id');

            $table->dropUnique(['github_installation_id', 'file_path', 'chunk_index']);
            $table->unique(['project_id', 'file_path', 'chunk_index']);

            $table->foreignId('project_id')->change();
        });
    }
};
