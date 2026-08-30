<?php

declare(strict_types=1);

namespace Modules\Blog\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Blog\Application\Queries\ListPublicBlogCategoriesHandler;
use Modules\Blog\Application\ReadModels\BlogCategoryPublicReadModel;

/**
 * Unauthenticated landing-page feed: every active category with its published
 * posts embedded (JSON relationship) and their count, shaped by the
 * {@see BlogCategoryPublicReadModel}
 * allowlist (BACKEND-PHP §4.1 + OWASP §12 property-level authorization — never
 * a raw model serialization). Reachable by anonymous internet traffic, hence
 * the tighter throttle at the route and no `auth`/`permission` middleware.
 */
final readonly class PublicBlogCategoryController
{
    /**
     * List public blog categories.
     *
     * Every active category with its published posts and post count, for the
     * landing page's category selector. Wrapped in a `data` envelope to match
     * the rest of this API's JSON shape.
     */
    public function index(ListPublicBlogCategoriesHandler $list): JsonResponse
    {
        return response()->json(['data' => $list->handle()]);
    }
}
