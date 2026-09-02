<?php

declare(strict_types=1);

namespace Modules\Availability\Domain\ValueObjects;

/**
 * Provenance of a date exception. A `Manual` row is a user-created override and
 * is never touched by the holiday sync; a `Holiday` row is system-materialised
 * from the country provider and is purged/rebuilt on a country change or the
 * yearly sync. This is the single source of truth for the `source` column.
 */
enum ExceptionSource: string
{
    case Manual = 'manual';

    case Holiday = 'holiday';
}
