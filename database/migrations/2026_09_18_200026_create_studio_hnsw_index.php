<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T-121 — HNSW index over the embedding column. Raw statement, pgsql-only,
 * non-concurrent (the table is empty at migration time and Laravel wraps
 * Postgres migrations in a transaction). Cosine operator `<=>` matches
 * `vector_cosine_ops`; any other operator silently skips the index.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'CREATE INDEX IF NOT EXISTS studio_embeddings_hnsw ON studio_embeddings '.
            'USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64) '.
            'WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS studio_embeddings_hnsw');
    }
};
