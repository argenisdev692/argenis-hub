<?php

declare(strict_types=1);

namespace Shared\Application\DTOs\Concerns;

/**
 * Identical `search` + `date_from` / `date_to` validation shared by every
 * list/export filter DTO across the modules (DRY — the same three rules were
 * previously re-declared in each filter). `date_to` may not precede
 * `date_from`; both filter on `created_at`.
 */
trait DateRangeFilterRules
{
    /**
     * @return array<string, array<int, string>>
     */
    protected static function dateRangeRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
