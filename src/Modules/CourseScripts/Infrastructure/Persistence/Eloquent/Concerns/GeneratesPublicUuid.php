<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Mints the public UUIDv7 identifier on create (project convention: internal
 * bigint `id`, public `uuid`).
 *
 * @mixin Model
 */
trait GeneratesPublicUuid
{
    protected static function bootGeneratesPublicUuid(): void
    {
        static::creating(static function (Model $model): void {
            if (empty($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid7());
            }
        });
    }
}
