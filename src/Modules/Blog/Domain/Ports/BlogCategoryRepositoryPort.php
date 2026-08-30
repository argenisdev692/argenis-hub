<?php

declare(strict_types=1);

namespace Modules\Blog\Domain\Ports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Blog\Application\DTOs\BlogCategoryFilterData;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;

interface BlogCategoryRepositoryPort
{
    /**
     * @return LengthAwarePaginator<int, BlogCategoryEloquentModel>
     */
    public function paginate(BlogCategoryFilterData $filters, int $perPage): LengthAwarePaginator;

    /**
     * Landing-page feed: every active category with its published post count.
     *
     * @return Collection<int, BlogCategoryEloquentModel>
     */
    public function listPublic(): Collection;

    public function findByUuid(string $uuid): ?BlogCategoryEloquentModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): BlogCategoryEloquentModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(BlogCategoryEloquentModel $category, array $attributes): BlogCategoryEloquentModel;

    public function softDelete(string $uuid): bool;

    public function restore(string $uuid): bool;

    /**
     * @param  array<int, string>  $uuids
     */
    public function bulkSoftDeleteByUuid(array $uuids): int;

    /**
     * @param  array<int, string>  $uuids
     */
    public function bulkRestoreByUuid(array $uuids): int;
}
