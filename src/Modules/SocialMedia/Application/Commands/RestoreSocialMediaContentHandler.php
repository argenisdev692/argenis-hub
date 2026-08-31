<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentRepositoryPort;

/**
 * Restores a soft-deleted content package by UUID. Authorization
 * (permission:RESTORE_SOCIAL_MEDIA) is enforced at the route.
 */
final readonly class RestoreSocialMediaContentHandler
{
    public function __construct(private SocialMediaContentRepositoryPort $content) {}

    public function handle(string $uuid): bool
    {
        return DB::transaction(fn (): bool => $this->content->restore($uuid));
    }
}
