<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Weekly `scout_*` backup (spec T077, mandatory on Supabase Free — no
 * automatic backups): `pg_dump` of the `scout_*` tables with the local
 * PostgreSQL client (same major as Supabase, T003) into a folder outside
 * the repo, keeping the 4 newest copies (noted in the RoPA, OP-10).
 */
final class LeadScoutBackupCommand extends Command
{
    protected $signature = 'lead-scout:backup {--dir= : Destination folder outside the repo (defaults to LEAD_SCOUT_BACKUP_DIR)} {--connection= : Database connection to dump (defaults to database.default)}';

    protected $description = 'Dump the scout_* tables with pg_dump (weekly, 4-copy retention)';

    public function handle(): int
    {
        $name = $this->option('connection');
        $name = is_string($name) && trim($name) !== '' ? trim($name) : (string) config('database.default');
        $connection = (array) config('database.connections.'.$name, []);

        if (($connection['driver'] ?? null) !== 'pgsql') {
            $this->error('Backups run against the pgsql database (Supabase). Point DB_CONNECTION at pgsql first.');

            return self::FAILURE;
        }

        $dir = $this->option('dir');
        $dir = is_string($dir) && trim($dir) !== ''
            ? trim($dir)
            : (string) (env('LEAD_SCOUT_BACKUP_DIR') ?: storage_path('app/private/lead-scout-backups'));

        if (! $this->isAllowedDestination($dir)) {
            $this->error('Backup destination must be outside the git-tracked tree (use storage/ or a path outside the project).');

            return self::FAILURE;
        }

        if (! is_dir($dir) && ! mkdir($dir, 0750, true) && ! is_dir($dir)) {
            $this->error("Cannot create backup directory: {$dir}.");

            return self::FAILURE;
        }

        $file = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'lead-scout-'.now()->format('Ymd-His').'.dump';
        $binary = (string) (env('LEAD_SCOUT_PG_DUMP') ?: 'pg_dump');

        $result = Process::env(['PGPASSWORD' => (string) ($connection['password'] ?? '')])->run([
            $binary,
            '--host='.$connection['host'],
            '--port='.(string) ($connection['port'] ?? '5432'),
            '--username='.$connection['username'],
            '--dbname='.$connection['database'],
            '--no-password',
            '--table=scout_*',
            '--file='.$file,
        ]);

        if (! $result->successful()) {
            $this->error('pg_dump failed: '.mb_substr(trim($result->errorOutput() ?: $result->output()), 0, 500));

            return self::FAILURE;
        }

        $this->pruneOldCopies($dir);
        $this->info("Backup written to {$file}.");

        return self::SUCCESS;
    }

    private function pruneOldCopies(string $dir): void
    {
        $files = glob(rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'lead-scout-*.dump') ?: [];
        rsort($files);

        foreach (array_slice($files, 4) as $stale) {
            if (is_file($stale)) {
                unlink($stale);
            }
        }
    }

    private function isAllowedDestination(string $dir): bool
    {
        $base = realpath(base_path()) ?: base_path();
        $storage = realpath(storage_path()) ?: storage_path();
        $probe = $dir;
        while (! is_dir($probe) && dirname($probe) !== $probe) {
            $probe = dirname($probe);
        }
        $resolved = realpath($probe) ?: $base;

        return ! str_starts_with((string) $resolved, (string) $base)
            || str_starts_with((string) $resolved, (string) $storage);
    }
}
