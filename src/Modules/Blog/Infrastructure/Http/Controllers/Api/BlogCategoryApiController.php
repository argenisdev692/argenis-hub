<?php

declare(strict_types=1);

namespace Modules\Blog\Infrastructure\Http\Controllers\Api;

use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Blog\Application\DTOs\BlogCategoryFilterData;
use Modules\Blog\Application\Queries\GetBlogCategoryHandler;
use Modules\Blog\Application\Queries\ListBlogCategoriesHandler;

/**
 * API endpoints for blog category lookup. Secondary Sanctum-authenticated
 * surface; the primary UI remains Inertia/web. Responses are documented by Scramble from the return types.
 *
 * Filters are documented from the injected {@see BlogCategoryFilterData}: Scramble reads a
 * `Data` parameter's rules directly, while the equivalent
 * `BlogCategoryFilterData::validateAndCreate($request)` call hides them — the
 * rules live in a static method the analyser cannot follow, so this endpoint
 * used to document `per_page` and nothing else. Injection validates
 * identically and is what the web controllers already do.
 */
final readonly class BlogCategoryApiController
{
    /**
     * List blog categories.
     *
     * Returns a paginated list of blog categories. `per_page` is capped at 100 to
     * bound resource consumption (OWASP API4).
     *
     * `search` matches the category name or its description.
     */
    #[QueryParameter(
        'per_page',
        description: 'Rows per page, clamped to 1–100.',
        type: 'int',
        default: 15,
    )]
    public function index(
        Request $request,
        BlogCategoryFilterData $filters,
        ListBlogCategoriesHandler $list,
    ): JsonResponse {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_ANY_BLOG_CATEGORIES'), 403);

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
