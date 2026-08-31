<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentRepositoryPort;
use Shared\Domain\Ports\AuditPort;

/**
 * Auto-publishes content packages whose `scheduled_at` has been reached.
 * Invoked by the `social-media:publish-scheduled` Artisan command, which
 * routes/console.php runs every minute — a package scheduled later today
 * flips to `published` on the next tick after its time arrives, while one
 * scheduled for a future date simply stays `scheduled` until its own tick is
 * due. Distinct from {@see PublishSocialMediaContentHandler}, which is the
 * manual, human-triggered publish action.
 */
final readonly class PublishDueSocialMediaContentHandler
{
    public function __construct(
        private SocialMediaContentRepositoryPort $content,
        private AuditPort $audit,
    ) {}

    public function handle(): int
    {
        $due = $this->content->dueForScheduledPublishing();

        foreach ($due as $content) {
            $scheduledAt = $content->scheduled_at?->toIso8601String();

            $published = DB::transaction(fn () => $this->content->update($content, [
                'status' => 'published',
                'published_at' => now(),
            ]));

            $this->audit->log(
                event: 'social_media.auto_published',
                subject: $published,
                properties: ['scheduled_at' => $scheduledAt],
                causer: null,
                logName: 'social_media',
            );
        }

        return $due->count();
    }
}
