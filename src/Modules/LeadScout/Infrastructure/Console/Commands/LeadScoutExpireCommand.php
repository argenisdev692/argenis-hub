<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\LeadScout\Application\Commands\ExpirePostingsHandler;

/**
 * Daily expiry pass (spec US-2 CA-3, T027).
 */
final class LeadScoutExpireCommand extends Command
{
    protected $signature = 'lead-scout:expire';

    protected $description = 'Expire job postings past the configured max age';

    public function handle(ExpirePostingsHandler $expire): int
    {
        $this->info("Expired {$expire->handle()} posting(s).");

        return self::SUCCESS;
    }
}
