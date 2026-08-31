<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentRepositoryPort;

/**
 * Soft-deletes a single content package by UUID. Generated assets (images,
 * voiceover) are intentionally kept on R2 so a restore is lossless.
 * Authorization (permission:DELETE_SOCIAL_MEDIA) is enforced at the route.
 */
final readonly class DeleteSocialMediaContentHandler
{
    public function __construct(private SocialMediaContentRepositoryPort $content) {}

    public function handle(string $uuid): bool
    {
        return DB::transaction(fn (): bool => $this->content->softDelete($uuid));
    }
}
