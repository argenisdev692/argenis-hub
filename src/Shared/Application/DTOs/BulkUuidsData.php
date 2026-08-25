<?php

declare(strict_types=1);

namespace Shared\Application\DTOs;

use Spatie\LaravelData\Data;

/**
 * Reusable contract for bulk soft-delete / soft-restore over a UUID array.
 *
 * Every module's `BulkDelete{Entity}Request` / `BulkRestore{Entity}Request`
 * reuses this DTO instead of re-declaring the same rules (DRY). Authorization
 * and domain events stay per-module — this only validates the payload shape.
 *
 * The 500 cap bounds the `whereIn` so a bulk action cannot be turned into an
 * unbounded query (OWASP — unrestricted resource consumption).
 */
final class BulkUuidsData extends Data
{
    /**
     * @param  array<int, string>  $uuids
     */
    public function __construct(
        public array $uuids,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'uuids' => ['required', 'array', 'min:1', 'max:500'],
            'uuids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
