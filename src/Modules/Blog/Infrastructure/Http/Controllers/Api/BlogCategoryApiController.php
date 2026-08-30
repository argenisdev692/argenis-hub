<?php

declare(strict_types=1);

namespace Modules\Blog\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Blog\Application\DTOs\BlogCategoryFilterData;
use Modules\Blog\Application\Queries\GetBlogCategoryHandler;
use Modules\Blog\Application\Queries\ListBlogCategoriesHandler;

/**
 * API endpoints for blog category lookup. Secondary Sanctum-authenticated
 * surface; the primary UI remains Inertia/web. Documented by Scramble via return
 * types + `auth:sanctum` detection — no manual annotations.
 */
final readonly class BlogCategoryApiController
{
    /**
     * List blog categories.
     *
     * Returns a paginated list of blog categories. `per_page` is capped at 100 to
     * bound resource consumption (OWASP API4).
     */
    public function index(Request $request, ListBlogCategoriesHandler $list): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_ANY_BLOG_CATEGORIES'), 403);

        $filters = BlogCategoryFilterData::validateAndCreate($request);

        return response()->json($list->handle($filters, min(max($request->integer('per_page', 15), 1), 100)));
    }

    /**
     * Show a blog category.
     *
     * Returns a single blog category by UUID.
     */
    public function show(Request $request, string $uuid, GetBlogCategoryHandler $get): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_BLOG_CATEGORIES'), 403);

        return response()->json(['data' => $get->handle($uuid)]);
    }
}
