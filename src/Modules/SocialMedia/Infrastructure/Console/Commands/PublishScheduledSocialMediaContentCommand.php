<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\SocialMedia\Application\Commands\PublishDueSocialMediaContentHandler;

/**
 * Scheduled every minute in routes/console.php — see
 * {@see PublishDueSocialMediaContentHandler} for the actual publish logic.
 */
final class PublishScheduledSocialMediaContentCommand extends Command
{
    protected $signature = 'social-media:publish-scheduled';

    protected $description = 'Publish social media content packages whose scheduled_at time has been reached.';

    public function handle(PublishDueSocialMediaContentHandler $handler): int
    {
        $published = $handler->handle();

        $this->info("Published {$published} scheduled social media content package(s).");

        return self::SUCCESS;
    }
}
