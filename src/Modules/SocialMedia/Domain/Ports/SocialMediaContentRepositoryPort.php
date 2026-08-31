<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Domain\Ports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\SocialMedia\Application\DTOs\SocialMediaContentFilterData;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;

interface SocialMediaContentRepositoryPort
{
    /**
     * @return LengthAwarePaginator<int, SocialMediaContentEloquentModel>
     */
    public function paginate(SocialMediaContentFilterData $filters, int $perPage): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?SocialMediaContentEloquentModel;

    /**
     * Anonymous landing-page feed: `published`, non-deleted packages only.
     * Kept separate from {@see self::paginate()} because the trust boundary and
     * the column allowlist genuinely diverge — this one is reachable by
     * unauthenticated internet traffic.
     *
     * @return LengthAwarePaginator<int, SocialMediaContentEloquentModel>
     */
    public function paginatePublic(int $perPage): LengthAwarePaginator;

    /**
     * One `published` package by UUID, for the public detail view. Null for a
     * draft/scheduled/suspended package — an unauthenticated caller must not
     * be able to distinguish "not published yet" from "does not exist".
     */
    public function findPublishedByUuid(string $uuid): ?SocialMediaContentEloquentModel;

    /**
     * Scheduled content packages whose `scheduled_at` has been reached (cron
     * auto-publish).
     *
     * @return Collection<int, SocialMediaContentEloquentModel>
     */
    public function dueForScheduledPublishing(): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): SocialMediaContentEloquentModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(SocialMediaContentEloquentModel $content, array $attributes): SocialMediaContentEloquentModel;

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
