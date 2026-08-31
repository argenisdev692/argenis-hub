<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\SocialMedia\Application\ReadModels\SocialMediaContentPublicReadModel;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentRepositoryPort;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;

/**
 * Single published package for the anonymous detail view — full body plus the
 * public slice of the per-platform copy.
 *
 * A draft, `generating`, scheduled or soft-deleted package raises the same 404
 * as a UUID that never existed: an unauthenticated caller must not be able to
 * probe for unpublished content by watching the status code
 * (OWASP §7 Insecure Design / API1).
 */
final readonly class GetPublicSocialMediaContentHandler
{
    public function __construct(private SocialMediaContentRepositoryPort $content) {}

    public function handle(string $uuid): SocialMediaContentPublicReadModel
    {
        $model = $this->content->findPublishedByUuid($uuid)
            ?? throw (new ModelNotFoundException)->setModel(SocialMediaContentEloquentModel::class, [$uuid]);

        return SocialMediaContentPublicReadModel::fromModel($model, includeBody: true);
    }
}
