<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\Post\Application\Commands\PublishDuePostsHandler;

/**
 * Scheduled every minute in routes/console.php — see
 * {@see PublishDuePostsHandler} for the actual publish logic.
 */
final class PublishScheduledPostsCommand extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Publish posts whose scheduled_at time has been reached.';

    public function handle(PublishDuePostsHandler $handler): int
    {
        $published = $handler->handle();

        $this->info("Published {$published} scheduled post(s).");

        return self::SUCCESS;
    }
}
