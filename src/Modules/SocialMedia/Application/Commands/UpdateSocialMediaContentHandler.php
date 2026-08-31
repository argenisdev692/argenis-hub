<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\SocialMedia\Application\DTOs\UpdateSocialMediaContentData;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentRepositoryPort;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;

/**
 * Persists the human review/edit pass over an AI-generated package.
 * Authorization (permission:UPDATE_SOCIAL_MEDIA) is enforced at the route.
 */
final readonly class UpdateSocialMediaContentHandler
{
    public function __construct(private SocialMediaContentRepositoryPort $content) {}

    public function handle(SocialMediaContentEloquentModel $content, UpdateSocialMediaContentData $data): SocialMediaContentEloquentModel
    {
        return DB::transaction(fn (): SocialMediaContentEloquentModel => $this->content->update($content, [
            'headline' => $data->headline,
            'body' => $data->body,
            'call_to_action' => $data->callToAction,
            'hashtags' => $data->hashtags,
            'status' => $data->status,
            'scheduled_at' => $data->scheduledAt,
            'published_at' => $data->status === 'published' ? now() : $content->published_at,
        ]));
    }
}
