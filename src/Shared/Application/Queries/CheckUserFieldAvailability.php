<?php

declare(strict_types=1);

namespace Shared\Application\Queries;

use App\Models\User;

/**
 * Single source of truth for realtime "is this username/email free?" checks,
 * shared by the register, profile and user-management forms (web + API).
 *
 * Only the two allowlisted columns can ever be queried. An optional `ignoreUuid`
 * excludes one row so an unchanged value (self-edit) reads as available. A
 * malformed email short-circuits to "unavailable" so the UI never reports a
 * value the backend would later reject as free.
 */
final class CheckUserFieldAvailability
{
    /**
     * @param  'username'|'email'  $field
     * @return bool true when the value is available (not taken)
     */
    public function __invoke(string $field, string $value, ?string $ignoreUuid = null): bool
    {
        if (! in_array($field, ['username', 'email'], true)) {
            return false;
        }

        if ($field === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        return ! User::query()
            ->where($field, $value)
            ->when($ignoreUuid !== null, fn ($query) => $query->where('uuid', '!=', $ignoreUuid))
            ->exists();
    }
}
