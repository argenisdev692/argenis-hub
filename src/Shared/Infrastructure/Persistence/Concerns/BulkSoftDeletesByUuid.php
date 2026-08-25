<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reusable bulk soft-delete / soft-restore for Eloquent repositories.
 *
 * The repository declares which model it operates on via {@see self::model()};
 * the model MUST use the {@see SoftDeletes} trait. Authorization and domain
 * events are NOT handled here — they stay in the per-module Bulk handler.
 */
trait BulkSoftDeletesByUuid
{
    /**
     * Fully-qualified Eloquent model (must use SoftDeletes) this repository
     * bulk-operates on.
     *
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    /**
     * Soft-delete every row whose `uuid` is in the set. Returns the affected count.
     *
     * @param  array<int, string>  $uuids
     */
    public function bulkSoftDeleteByUuid(array $uuids): int
    {
        if ($uuids === []) {
            return 0;
        }

        return $this->model()::query()
            ->whereIn('uuid', $uuids)
            ->delete();
    }

    /**
     * Restore every soft-deleted row whose `uuid` is in the set. Returns the
     * affected count.
     *
     * @param  array<int, string>  $uuids
     */
    public function bulkRestoreByUuid(array $uuids): int
    {
        if ($uuids === []) {
            return 0;
        }

        return $this->model()::query()
            ->withTrashed()
            ->whereIn('uuid', $uuids)
            ->restore();
    }
}
