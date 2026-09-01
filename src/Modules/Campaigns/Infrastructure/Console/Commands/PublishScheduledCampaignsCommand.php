<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\Campaigns\Application\Commands\PublishDueCampaignsHandler;

/**
 * Scheduled every minute in routes/console.php — see
 * {@see PublishDueCampaignsHandler} for the actual publish logic.
 */
final class PublishScheduledCampaignsCommand extends Command
{
    protected $signature = 'campaigns:publish-scheduled';

    protected $description = 'Publish campaigns whose scheduled_at time has been reached.';

    public function handle(PublishDueCampaignsHandler $handler): int
    {
        $published = $handler->handle();

        $this->info("Published {$published} scheduled campaign(s).");

        return self::SUCCESS;
    }
}
