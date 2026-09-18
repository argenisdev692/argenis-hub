<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Supabase;

use Illuminate\Support\Facades\DB;

/**
 * Supabase hardening for every `scout_*` table (plan §4.2, research R12).
 *
 * The Data API exposes the `public` schema: a table without RLS is readable
 * AND writable by anyone holding the public key. `scout_contacts` holds
 * personal data, so every migration calls `protect()`:
 * `ENABLE ROW LEVEL SECURITY` + `REVOKE ALL FROM anon, authenticated`
 * (only when those roles exist — they do not on local PostgreSQL).
 * Laravel connects as the table owner, so RLS never affects the app.
 * No-op on sqlite (the default test driver).
 *
 * WARNING: never add a `change()`/`dropColumn` migration on a `scout_*`
 * table carrying a raw partial unique index — sqlite rebuilds the table
 * and silently drops the predicate (verified 2026-09-17: the contacts
 * primary index came back full-unique). Fold nullability into the
 * creating migration instead.
 */
final readonly class SupabaseRls
{
    /**
     * @param  list<string>  $extraRoles
     */
    public static function protect(string $table, array $extraRoles = []): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");

        foreach ([...$extraRoles, 'anon', 'authenticated'] as $role) {
            $exists = DB::selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [$role]);

            if ($exists !== null) {
                DB::statement("REVOKE ALL ON {$table} FROM {$role}");
            }
        }
    }
}
