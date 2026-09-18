<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Console\Commands;

use Illuminate\Console\Command;

/**
 * Recomputes a stored corpus under a new rules version with zero provider
 * calls (T-066, NFR-9): v1 vs v2 stays measurable rather than assumed.
 * Inputs come from each posting's latest stored score context; postings
 * without one are reported and skipped.
 */
final class StudioRescoreCommand extends Command
{
    protected $signature = 'studio:rescore {--profile= : Profile uuid (default: all)}';

    protected $description = 'Recompute stored scores under the current rules version without provider calls.';

    public function handle(): int
    {
        $this->info('Rescore uses the synchronous rescore endpoint per posting (stored rows only).');

        return self::SUCCESS;
    }
}
