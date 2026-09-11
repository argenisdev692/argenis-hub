<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\VideoEdits\Application\Commands\PurgeExpiredVideoEditSourcesHandler;

/**
 * Deletes source recordings that are no longer needed (spec 001-video-edit
 * FR-9, FR-10). Scheduled hourly in routes/console.php; registered by
 * VideoEditsServiceProvider.
 */
final class PurgeExpiredVideoEditSourcesCommand extends Command
{
    protected $signature = 'video-edits:purge-sources';

    protected $description = 'Delete video edit sources whose retry window has closed or that survived publishing.';

    public function handle(PurgeExpiredVideoEditSourcesHandler $purge): int
    {
        $this->components->info(sprintf('Purged the sources of %d video edit(s).', $purge->handle()));

        return self::SUCCESS;
    }
}
