<?php

declare(strict_types=1);

namespace Modules\Blog\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Blog\Application\Commands\BulkDeleteBlogCategoriesHandler;
use Modules\Blog\Application\Commands\BulkRestoreBlogCategoriesHandler;
use Modules\Blog\Application\Commands\CreateBlogCategoryHandler;
use Modules\Blog\Application\Commands\DeleteBlogCategoryHandler;
use Modules\Blog\Application\Commands\RestoreBlogCategoryHandler;
use Modules\Blog\Application\Commands\UpdateBlogCategoryHandler;
use Modules\Blog\Application\DTOs\BlogCategoryData;
use Modules\Blog\Application\DTOs\BlogCategoryFilterData;
use Modules\Blog\Application\Queries\GetBlogCategoryHandler;
use Modules\Blog\Application\Queries\ListBlogCategoriesHandler;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Blog category management. Every route is authorized via
 * `permission:*_BLOG_CATEGORIES` middleware (not roles) — see Routes. Controller
 * stays thin: validate → handler → response, branching Inertia vs JSON.
 */
final readonly class BlogCategoryController
{
    public function index(Request $request, ListBlogCategoriesHandler $list): InertiaResponse|JsonResponse
    {
        $filters = BlogCategoryFilterData::validateAndCreate($request);
        $categories = $list->handle($filters, min(max($request->integer('per_page', 15), 1), 100));

        return $request->expectsJson()
            ? response()->json($categories)
            : Inertia::render('blog-categories/Index', ['blogCategories' => $categories, 'filters' => $filters]);
    }

    public function show(string $uuid, GetBlogCategoryHandler $get): InertiaResponse|JsonResponse
    {
        $category = $get->handle($uuid);

        return request()->expectsJson()
            ? response()->json(['data' => $category])
            : Inertia::render('blog-categories/Show', ['blogCategory' => $category]);
    }

    public function store(Request $request, BlogCategoryData $data, CreateBlogCategoryHandler $create): RedirectResponse
    {
        (void) $create->handle($data, (int) $request->user()->id);

        return back()->with('success', __('Blog category created.'));
    }

    public function update(string $uuid, BlogCategoryData $data, GetBlogCategoryHandler $get, UpdateBlogCategoryHandler $update): RedirectResponse
    {
        (void) $update->handle($get->handle($uuid), $data);

        return back()->with('success', __('Blog category updated.'));
    }

    public function destroy(string $uuid, DeleteBlogCategoryHandler $delete): RedirectResponse
    {
        (void) $delete->handle($uuid);

        return back()->with('success', __('Blog category suspended.'));
    }

    public function restore(string $uuid, RestoreBlogCategoryHandler $restore): RedirectResponse
    {
        (void) $restore->handle($uuid);

        return back()->with('success', __('Blog category restored.'));
    }

    public function bulkDelete(BulkUuidsData $data, BulkDeleteBlogCategoriesHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data);

        return back()->with('success', __(':count blog categories suspended.', ['count' => $count]));
    }

    public function bulkRestore(BulkUuidsData $data, BulkRestoreBlogCategoriesHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data);

        return back()->with('success', __(':count blog categories restored.', ['count' => $count]));
    }
}
