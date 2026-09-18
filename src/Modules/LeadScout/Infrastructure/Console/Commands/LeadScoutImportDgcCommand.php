<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\LeadScout\Application\Commands\ImportDgcListHandler;

/**
 * DGC opposition-list import (spec FR-17, T074, Lei 41/2004 art. 13-B):
 * CSV/XLSX (≤ 10 MB) matched by NIPC, domain or normalized name. Renewed
 * every quarter (OP-16); `ChannelAdvisor` blocks PT email while the last
 * import is older than 3 months.
 */
final class LeadScoutImportDgcCommand extends Command
{
    protected $signature = 'lead-scout:import-dgc {file : CSV/XLSX path (max 10 MB)} {--period= : List period label (defaults to the current quarter, e.g. 2026-Q3)}';

    protected $description = 'Import the DGC opposition list into suppressions (source dgc_list)';

    public function handle(ImportDgcListHandler $import): int
    {
        try {
            $report = $import->handle(
                (string) $this->argument('file'),
                is_string($this->option('period')) && trim((string) $this->option('period')) !== ''
                    ? trim((string) $this->option('period'))
                    : null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$report['imported']}, already listed {$report['skipped']}, invalid {$report['invalid']}.");

        return self::SUCCESS;
    }
}
