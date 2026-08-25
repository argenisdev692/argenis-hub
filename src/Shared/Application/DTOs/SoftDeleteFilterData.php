<?php

declare(strict_types=1);

namespace Shared\Application\DTOs;

use Shared\Application\DTOs\Concerns\DateRangeFilterRules;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Reusable base for soft-delete list/export filters — the common
 * `search` + `status` + `created_at` window shape shared by the Users, Roles,
 * Permissions and Blog-category filters.
 *
 * Mirrors the {@see BulkUuidsData} precedent: the shared shape lives here while
 * the module-specific bit (the `status` enum) stays local. Subclasses only
 * override {@see self::rules()}, spreading {@see self::baseRules()} and adding
 * their own `status` `in:` list, so no `scopeApplyFilters()` signature (which
 * type-hints the concrete subclass) has to change.
 *
 * `MapName(SnakeCaseMapper)` is REQUIRED, not decorative: the wire format is
 * snake_case (`date_from` / `date_to`, matching {@see DateRangeFilterRules} and
 * the frontend `Filters` shape) while the properties are camelCase. Without the
 * mapper the rules still validate `date_from`, but hydration looks for
 * `dateFrom`, leaves it `null`, and the date filter silently never applies.
 * Mapping both directions also keeps the generated TypeScript snake_case.
 */
#[MapName(SnakeCaseMapper::class)]
abstract class SoftDeleteFilterData extends Data
{
    use DateRangeFilterRules;

    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
    ) {}

    /**
     * Common rules (search + created_at window). Subclasses spread this and add
     * their module-specific `status` rule.
     *
     * @return array<string, array<int, string>>
     */
    protected static function baseRules(): array
    {
        return static::dateRangeRules();
    }
}
