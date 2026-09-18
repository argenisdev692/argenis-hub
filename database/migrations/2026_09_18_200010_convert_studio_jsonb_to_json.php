<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite-portability fix (T-112, CHG-17): the project convention is
 * `$table->json()` (LeadScout precedent), not `jsonb()`. The create
 * migrations now declare `json`; this migration converts the already-created
 * PostgreSQL columns. No-op on any other driver.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const array COLUMNS = [
        'studio_profiles' => [
            'accepted_remote_scopes',
            'stack_must',
            'stack_reject',
            'never_seed',
            'search_languages',
            'geography_prefer',
            'geography_deny',
            'language_levels',
            'protected_block',
            'seniority_band',
            'rules',
        ],
        'studio_postings' => ['d_disc_components'],
        'studio_scores' => ['h_components', 's_components', 'd_components', 'caps_applied', 'o_components'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $type = DB::selectOne(
                    'SELECT format_type(atttypid, atttypmod) AS type FROM pg_attribute WHERE attrelid = ?::regclass AND attname = ?',
                    [$table, $column],
                );

                if (($type->type ?? '') === 'jsonb') {
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE json USING {$column}::json");
                }
            }
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: json is the portable canonical type.
    }
};
