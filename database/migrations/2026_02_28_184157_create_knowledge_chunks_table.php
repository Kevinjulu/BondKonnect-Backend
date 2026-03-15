<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable pgvector extension if it's PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('source_file'); // e.g., ui_map.md
            $table->string('section_title'); // e.g., Core Navigation
            $table->text('content');
            // Using raw SQL for vector column as it's not standard Laravel
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE knowledge_chunks ADD COLUMN embedding vector(1536)');
            // Add index for fast similarity search (IVFFlat or HNSW)
            // Note: HNSW is better but might require more memory. IVFFlat is standard.
            DB::statement('CREATE INDEX ON knowledge_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
        } else {
            // Fallback for MySQL/SQLite in local dev (though similarity search won't work perfectly)
            Schema::table('knowledge_chunks', function (Blueprint $table) {
                $table->json('embedding')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
