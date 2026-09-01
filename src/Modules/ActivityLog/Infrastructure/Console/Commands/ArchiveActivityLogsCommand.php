<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Infrastructure\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Modules\ActivityLog\Providers\ActivityLogServiceProvider;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\StoragePort;
use Spatie\Activitylog\Models\Activity;

/**
 * Tiered retention for the activity-log trail: rows older than the hot window
 * (default 90 days) are streamed to cold storage (R2) as gzipped NDJSON, then
 * hard-deleted from the database. The purge itself is meta-audited so the trail
 * always records how it was trimmed.
 *
 * Registered by {@see ActivityLogServiceProvider}
 * and scheduled daily at 03:00 in `routes/console.php`. Run with `--dry-run` to
 * preview the count without touching storage or the table.
 *
 * The archive is streamed row-by-row through a gzip file handle and uploaded
 * with {@see StoragePort::putFromPath()} — the whole payload never sits in PHP
 * memory, so the command is safe on an arbitrarily large backlog.
 */
final class ArchiveActivityLogsCommand extends Command
{
    protected $signature = 'activity-log:archive
        {--days=90 : Age (in days) beyond which rows are archived and purged}
        {--dry-run : Report what would be archived without writing or deleting}';

    protected $description = 'Archive activity-log rows past the hot window to R2, then purge them from the database.';

    public function handle(StoragePort $storage, AuditPort $audit): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = CarbonImmutable::now()->subDays($days)->startOfDay();

        $query = Activity::query()->where('created_at', '<', $cutoff);
        $total = $query->clone()->count();

        if ($total === 0) {
            $this->info("Nothing to archive — no activity older than {$days} days.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("[dry-run] {$total} activity rows older than {$cutoff->toDateString()} would be archived and purged.");

            return self::SUCCESS;
        }

        $objectKey = sprintf(
            'archives/activity-log/%s_before_%s.ndjson.gz',
            CarbonImmutable::now()->format('Y-m-d_His'),
            $cutoff->format('Y-m-d'),
        );

        $tmpPath = (string) tempnam(sys_get_temp_dir(), 'actlog_');
        $gz = gzopen($tmpPath, 'wb9');

        if ($gz === false) {
            @unlink($tmpPath);
            $this->error('Could not open a temporary file for the archive.');

            return self::FAILURE;
        }

        // try/finally: a failed chunk or a failed upload must never strand the
        // gzip handle or the temp file — this command runs nightly, unattended.
        try {
            $query->clone()->orderBy('id')->chunkById(1000, function (Collection $activities) use ($gz): void {
                foreach ($activities as $activity) {
                    gzwrite($gz, json_encode(
                        $activity->attributesToArray(),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    ).PHP_EOL);
                }
            });

            gzclose($gz);
            $gz = null;

            $storage->putFromPath($objectKey, $tmpPath, 'private');
        } finally {
            if ($gz !== null) {
                gzclose($gz);
            }

            @unlink($tmpPath);
        }

        $deleted = $query->clone()->delete();

        $audit->log(
            'activity_log.archived',
            null,
            [
                'archived_rows' => $deleted,
                'archived_before' => $cutoff->toDateString(),
                'window_days' => $days,
                'object_key' => $objectKey,
                'disk' => config('filesystems.cloud'),
            ],
            null,
            'system',
        );

        $this->info("Archived {$deleted} rows to {$objectKey} and purged them from the database.");

        return self::SUCCESS;
    }
}
