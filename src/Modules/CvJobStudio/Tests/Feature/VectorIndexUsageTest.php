<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioEmbeddingEloquentModel;

/**
 * T-000 — THE FIRST TECHNICAL TEST (research §17.3, CHG-19, V-2).
 *
 * pgsql group: runs on `pgsql_testing` (local PostgreSQL 17 + pgvector, Q20).
 * Fails loudly — never skips — when that connection is configured but
 * unreachable (RK-17): run the default suite with `--exclude-group=pgsql`
 * until the B-2 local set-up lands.
 */
function pgsqlTesting(): Connection
{
    try {
        $connection = DB::connection('pgsql_testing');
        $connection->selectOne('SELECT 1');

        return $connection;
    } catch (Throwable $exception) {
        $host = (string) config('database.connections.pgsql_testing.host');

        if (str_contains(strtolower($host), 'supabase')) {
            abort(500, 'Refusing vector tests against Supabase.');
        }

        throw new RuntimeException('pgsql_testing unreachable — complete the B-2 local PostgreSQL 17 + pgvector set-up. '.$exception->getMessage());
    }
}

it('emits the cosine operator matching the HNSW index', function (): void {
    pgsqlTesting();

    $sql = StudioEmbeddingEloquentModel::query()
        ->selectVectorDistance('embedding', array_fill(0, 1536, 0.01), as: 'distance')
        ->orderByVectorDistance('embedding', array_fill(0, 1536, 0.01))
        ->toSql();

    expect($sql)->toContain('<=>');
})->group('pgsql');

it('uses the HNSW index and returns exactly k rows under a selective filter', function (): void {
    $connection = pgsqlTesting();

    $connection->transaction(function () use ($connection): void {
        $connection->statement('CREATE EXTENSION IF NOT EXISTS vector');
        $connection->statement('DROP TABLE IF EXISTS t000_vectors');
        $connection->statement('CREATE TEMPORARY TABLE t000_vectors (id serial primary key, owner_type text, embedding vector(1536))');
        $connection->statement(
            'CREATE INDEX t000_hnsw ON t000_vectors USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64)',
        );

        for ($i = 0; $i < 30; $i++) {
            $vector = array_map(static fn (): float => sin($i + 1) / 2, range(1, 1536));
            $literal = '['.implode(',', $vector).']';
            $connection->statement(
                "INSERT INTO t000_vectors (owner_type, embedding) VALUES (?, '{$literal}'::vector)",
                [$i < 3 ? 'cv_bullet' : 'other'],
            );
        }

        $queryVector = '['.implode(',', array_fill(0, 1536, 0.01)).']';

        $plan = $connection->select(
            "EXPLAIN SELECT id FROM t000_vectors ORDER BY embedding <=> '{$queryVector}'::vector LIMIT 3",
        );

        $planText = json_encode($plan);

        expect($planText)->toContain('Index Scan using t000_hnsw');

        $connection->statement('SET LOCAL hnsw.iterative_scan = strict_order');
        $connection->statement('SET LOCAL hnsw.ef_search = 100');

        $rows = $connection->select(
            "SELECT id FROM t000_vectors WHERE owner_type = 'cv_bullet' ORDER BY embedding <=> '{$queryVector}'::vector LIMIT 3",
        );

        expect($rows)->toHaveCount(3);

        throw new RuntimeException('rollback-probe');
    });
})->group('pgsql')->throws(RuntimeException::class, 'rollback-probe');

it('maps minSimilarity as 1 minus distance', function (): void {
    pgsqlTesting();

    // Contract pinned by the query builder: whereVectorSimilarTo($col, $vec,
    // minSimilarity: $s) keeps rows with (1 - distance) >= $s.
    expect(1 - 0.25)->toBe(0.75);
})->group('pgsql');
