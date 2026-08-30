<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Modules\Post\Application\DTOs\PostFilterData;
use Modules\Post\Domain\Ports\PostRepositoryPort;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;
use Shared\Infrastructure\Persistence\Concerns\BulkSoftDeletesByUuid;

/**
 * Eloquent adapter for {@see PostRepositoryPort}. Bulk soft-delete/restore are
 * inherited from the Shared {@see BulkSoftDeletesByUuid} trait (DRY). The
 * `category_uuid` the Application layer works with is resolved to the internal
 * `category_id` FK here — the only layer allowed to touch Eloquent models.
 */
final class EloquentPostRepository implements PostRepositoryPort
{
    use BulkSoftDeletesByUuid;

    /**
     * @return class-string<PostEloquentModel>
     */
    protected function model(): string
    {
        return PostEloquentModel::class;
    }

    public function paginate(PostFilterData $filters, int $perPage): LengthAwarePaginator
    {
        return PostEloquentModel::query()
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->with(['category:id,uuid,blog_category_name', 'user:id,first_name,last_name'])
            ->select([
                'id', 'uuid', 'post_title', 'post_title_slug', 'post_excerpt', 'post_cover_image',
                'category_id', 'user_id', 'post_status', 'scheduled_at', 'published_at',
                'is_ai_generated', 'seo_score', 'eeat_score', 'human_writing_index',
                'created_at', 'deleted_at',
            ])
            ->orderBy($filters->resolvedSortField(), $filters->resolvedSortDirection())
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUuid(string $uuid): ?PostEloquentModel
    {
        return PostEloquentModel::withTrashed()
            ->with(['category:id,uuid,blog_category_name', 'user:id,first_name,last_name'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function paginatePublic(?string $categoryUuid, int $perPage): LengthAwarePaginator
    {
        // No `user` eager-load: the public ReadModel does not expose the
        // author, so loading first_name/last_name would only pull PII the
        // feed discards.
        return PostEloquentModel::query()
            ->published()
            ->when(
                $categoryUuid !== null,
                fn ($q) => $q->whereHas('category', fn ($c) => $c->where('uuid', $categoryUuid)),
            )
            ->with('category:id,uuid,blog_category_name')
            ->select([
                'id', 'uuid', 'post_title', 'post_title_slug', 'post_excerpt', 'post_cover_image',
                'meta_title', 'meta_description', 'meta_keywords', 'category_id', 'published_at',
            ])
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findPublicBySlug(string $slug): ?PostEloquentModel
    {
        return PostEloquentModel::query()
            ->published()
            ->with('category:id,uuid,blog_category_name')
            ->where('post_title_slug', $slug)
            ->first();
    }

    public function dueForScheduledPublishing(): Collection
    {
        return PostEloquentModel::query()
            ->where('post_status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();
    }

    public function slugExists(string $slug, ?string $ignoreUuid = null): bool
    {
        return PostEloquentModel::withTrashed()
            ->where('post_title_slug', $slug)
            ->when($ignoreUuid !== null, fn ($q) => $q->where('uuid', '!=', $ignoreUuid))
            ->exists();
    }

    public function create(array $attributes): PostEloquentModel
    {
        return PostEloquentModel::query()->create($this->resolveCategory($attributes));
    }

    public function update(PostEloquentModel $post, array $attributes): PostEloquentModel
    {
        $post->update($this->resolveCategory($attributes));

        return $post->refresh();
    }

    public function softDelete(string $uuid): bool
    {
        return (bool) PostEloquentModel::query()->where('uuid', $uuid)->delete();
    }

    public function restore(string $uuid): bool
    {
        return (bool) PostEloquentModel::onlyTrashed()->where('uuid', $uuid)->restore();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function resolveCategory(array $attributes): array
    {
        if (! array_key_exists('category_uuid', $attributes)) {
            return $attributes;
        }

        $categoryUuid = $attributes['category_uuid'];
        unset($attributes['category_uuid']);

        $attributes['category_id'] = $categoryUuid !== null
            ? BlogCategoryEloquentModel::query()->where('uuid', $categoryUuid)->value('id')
            : null;

        return $attributes;
    }
}
