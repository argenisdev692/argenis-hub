<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentRepositoryPort;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;
use Shared\Domain\Ports\AuditPort;

/**
 * Marks a reviewed package as `published` (the row records the decision — the
 * actual posting to each social network is a manual, external step in v1).
 * The user may publish from `ready` or `needs_review` alike: `all_scores_pass`
 * is informational, not a hard gate, since a human is reviewing before this
 * call. Authorization (permission:PUBLISH_SOCIAL_MEDIA) is enforced at the route.
 */
final readonly class PublishSocialMediaContentHandler
{
    public function __construct(
        private SocialMediaContentRepositoryPort $content,
        private AuditPort $audit,
    ) {}

    public function handle(SocialMediaContentEloquentModel $content, ?object $causer = null): SocialMediaContentEloquentModel
    {
        $published = DB::transaction(fn (): SocialMediaContentEloquentModel => $this->content->update($content, [
            'status' => 'published',
            'published_at' => now(),
        ]));

        $this->audit->log(
            event: 'social_media.published',
            subject: $published,
            properties: ['all_scores_pass' => $published->all_scores_pass],
            causer: $causer,
            logName: 'social_media',
        );

        return $published;
    }
}
