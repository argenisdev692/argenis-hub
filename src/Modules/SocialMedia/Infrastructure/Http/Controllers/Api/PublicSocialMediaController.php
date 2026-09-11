<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Http\Controllers\Api;

use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Modules\SocialMedia\Application\Queries\GetPublicSocialMediaContentHandler;
use Modules\SocialMedia\Application\Queries\ListPublicSocialMediaContentHandler;
use Modules\SocialMedia\Application\ReadModels\SocialMediaContentPublicReadModel;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Unauthenticated social-media feed: `published` packages only, shaped by the
 * {@see SocialMediaContentPublicReadModel} allowlist (OWASP §12 property-level
 * authorization — never a raw model serialization). Reachable by anonymous
 * internet traffic, hence the tighter `throttle:landing-public` at the route
 * and no `auth`/`permission` middleware.
 *
 * Responses are documented by Scramble from the return types. The feed takes no
 * filters — only paging — so there is no filter `Data` object to inject here.
 */
final readonly class PublicSocialMediaController
{
    /**
     * List public social media content.
     *
     * Paginated feed of published content packages. `body` and `platforms` are
     * omitted here for bandwidth; fetch a single package for the full copy.
     * `per_page` is capped at 100 to bound resource consumption (OWASP API4).
     *
     * @return PaginatedDataCollection<int, SocialMediaContentPublicReadModel>
     */
    #[QueryParameter(
        'per_page',
        description: 'Rows per page, clamped to 1–100.',
        type: 'int',
        default: 15,
    )]
    public function index(Request $request, ListPublicSocialMediaContentHandler $list): PaginatedDataCollection
    {
        return $list->handle(
            perPage: min(max($request->integer('per_page', 15), 1), 100),
        );
    }

    /**
     * Show a public social media content package.
     *
     * Returns one published package by UUID, including the full body and the
     * public slice of the per-platform copy. Anything not published returns
     * 404, the same as an unknown UUID.
     */
    public function show(string $uuid, GetPublicSocialMediaContentHandler $get): SocialMediaContentPublicReadModel
    {
        return $get->handle($uuid);
    }
}
