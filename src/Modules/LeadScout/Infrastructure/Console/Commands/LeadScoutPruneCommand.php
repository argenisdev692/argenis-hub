<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\LeadScout\Application\Commands\PruneLeadContactsHandler;

/**
 * Daily retention pass (spec FR-27, T070).
 */
final class LeadScoutPruneCommand extends Command
{
    protected $signature = 'lead-scout:prune';

    protected $description = 'Anonymize expired decisors and prune old fetched markdown';

    public function handle(PruneLeadContactsHandler $prune): int
    {
        $report = $prune->handle();

        $this->info("Anonymized {$report['contacts']} contact(s), pruned {$report['pages']} page(s).");

        return self::SUCCESS;
    }
}
