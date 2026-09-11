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
     * The comments below are PUBLIC API DOCUMENTATION, not notes to the next
     * maintainer.
     *
     * Scramble harvests the comment above each rule as that query parameter's
     * `description`, and every list endpoint that injects a filter DTO built on
     * this trait inherits them — so one edit here documents `search` and the
     * date window across the whole API surface. Keep maintainer asides in this
     * docblock, never in the array.
     *
     * @return array<string, array<int, string>>
     */
    protected static function dateRangeRules(): array
    {
        return [
            // Free-text match. Which columns it searches is per-module; see the
            // endpoint's own description.
            'search' => ['nullable', 'string', 'max:255'],
            // Inclusive start of the date window. May not fall after `date_to`.
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            // Inclusive end of the date window. May not fall before `date_from`.
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
