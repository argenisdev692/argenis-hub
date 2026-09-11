<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Http\Controllers\Api;

use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Modules\Post\Application\Queries\GetPublicPostHandler;
use Modules\Post\Application\Queries\ListPublicPostsHandler;
use Modules\Post\Application\ReadModels\PostPublicReadModel;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Unauthenticated landing-page feed: `published` posts only, shaped by the
 * {@see PostPublicReadModel} allowlist (BACKEND-PHP §4.1 + OWASP §12
 * property-level authorization — never a raw model serialization). Reachable
 * by anonymous internet traffic, hence the tighter throttle at the route and
 * no `auth`/`permission` middleware.
 */
final readonly class PublicPostController
{
    /**
     * List public posts.
     *
     * Paginated feed of published posts for the landing page, optionally
     * scoped to one category via `?category_uuid=`. `per_page` is capped at
     * 100 to bound resource consumption (OWASP API4).
     *
     * @return PaginatedDataCollection<int, PostPublicReadModel>
     */
    #[QueryParameter(
        'per_page',
        description: 'Rows per page, clamped to 1–100.',
        type: 'int',
        default: 15,
    )]
    #[QueryParameter(
        'category_uuid',
        // Annotated rather than injected: there is no filter `Data` object
        // here to read rules from — the handler takes two scalars — and
        // Scramble does not infer a `$request->string(...)` read.
        description: 'Restrict the feed to one blog category.',
        type: 'string',
        format: 'uuid',
    )]
    public function index(Request $request, ListPublicPostsHandler $list): PaginatedDataCollection
    {
        return $list->handle(
            categoryUuid: $request->string('category_uuid')->value() ?: null,
            perPage: min(max($request->integer('per_page', 15), 1), 100),
        );
    }

    /**
     * Show a public post.
     *
     * Returns a single published post by its slug, including full content —
     * for the landing page's article detail view.
     */
    public function show(string $slug, GetPublicPostHandler $get): PostPublicReadModel
    {
        return $get->handle($slug);
    }
}
