<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Modules\Post\Application\Commands\BulkDeletePostsHandler;
use Modules\Post\Application\Commands\BulkRestorePostsHandler;
use Modules\Post\Application\Commands\CreatePostHandler;
use Modules\Post\Application\Commands\DeletePostHandler;
use Modules\Post\Application\Commands\RestorePostHandler;
use Modules\Post\Application\Commands\UpdatePostHandler;
use Modules\Post\Application\DTOs\PostData;
use Modules\Post\Application\DTOs\PostFilterData;
use Modules\Post\Application\Queries\GetPostHandler;
use Modules\Post\Application\Queries\ListPostsHandler;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Post management. Every route is authorized via `permission:*_POSTS` middleware
 * (not roles) — see Routes. Create/edit are dedicated pages (not a modal) since
 * the content field is long-form and pairs with the AI assist panel. Controller
 * stays thin: validate → handler → response, branching Inertia vs JSON.
 */
final readonly class PostController
{
    public function index(Request $request, ListPostsHandler $list): InertiaResponse|JsonResponse
    {
        $filters = PostFilterData::validateAndCreate($request);
        $posts = $list->handle($filters, min(max($request->integer('per_page', 15), 1), 100));

        return match ($request->expectsJson()) {
            true => response()->json($posts),
            false => Inertia::render('posts/Index', [
                'posts' => $posts,
                'filters' => $filters,
                'categories' => $this->categoryOptions(),
            ]),
        };
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('posts/Create', [
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function edit(string $uuid, GetPostHandler $get): InertiaResponse
    {
        return Inertia::render('posts/Edit', [
            'post' => $get->handle($uuid),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(Request $request, PostData $data, CreatePostHandler $create): RedirectResponse
    {
        (void) $create->handle($data, (int) $request->user()->id);

        return redirect()->route('posts.index')->with('success', __('Post created.'));
    }

    public function update(string $uuid, PostData $data, GetPostHandler $get, UpdatePostHandler $update): RedirectResponse
    {
        (void) $update->handle($get->handle($uuid), $data);

        return redirect()->route('posts.index')->with('success', __('Post updated.'));
    }

    public function destroy(string $uuid, DeletePostHandler $delete): RedirectResponse
    {
        (void) $delete->handle($uuid);

        return back()->with('success', __('Post suspended.'));
    }

    public function restore(string $uuid, RestorePostHandler $restore): RedirectResponse
    {
        (void) $restore->handle($uuid);

        return back()->with('success', __('Post restored.'));
    }

    public function bulkDelete(BulkUuidsData $data, BulkDeletePostsHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data);

        return back()->with('success', __(':count posts suspended.', ['count' => $count]));
    }

    public function bulkRestore(BulkUuidsData $data, BulkRestorePostsHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data);

        return back()->with('success', __(':count posts restored.', ['count' => $count]));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function categoryOptions(): array
    {
        return BlogCategoryEloquentModel::query()
            ->select(['uuid', 'blog_category_name'])
            ->orderBy('blog_category_name')
            ->get()
            ->map(fn (BlogCategoryEloquentModel $c): array => [
                'value' => $c->uuid,
                'label' => (string) $c->blog_category_name,
            ])
            ->all();
    }
}
