<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\Queries;

use Modules\SocialMedia\Application\ReadModels\SocialMediaContentPublicReadModel;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentRepositoryPort;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Anonymous social-media feed: `published` packages only, no authentication.
 * Kept as its own query rather than reusing {@see ListSocialMediaContentHandler}
 * because the trust boundary genuinely diverges — this one is reachable by
 * anonymous internet traffic, so the response is built from the
 * {@see SocialMediaContentPublicReadModel} allowlist instead of the admin
 * column set (BACKEND-PHP §7 Insecure Design).
 *
 * Deliberately NOT cached, unlike Post's public feed: that one is hit on every
 * landing-page render, this one backs a low-traffic content archive. A cache
 * here would need its own invalidation port wired into six write handlers to
 * buy nothing measurable — the `throttle:landing-public` limiter plus the
 * narrow column select is the right amount of machinery for now (YAGNI).
 */
final readonly class ListPublicSocialMediaContentHandler
{
    public function __construct(private SocialMediaContentRepositoryPort $content) {}

    /**
     * @return PaginatedDataCollection<int, SocialMediaContentPublicReadModel>
     */
    public function handle(int $perPage = 15): PaginatedDataCollection
    {
        $paginator = $this->content->paginatePublic($perPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(
                static fn ($model): SocialMediaContentPublicReadModel => SocialMediaContentPublicReadModel::fromModel($model),
            ),
        );

        return SocialMediaContentPublicReadModel::collect($paginator, PaginatedDataCollection::class);
    }
}
