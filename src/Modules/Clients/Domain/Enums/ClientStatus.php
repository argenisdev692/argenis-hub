<?php

declare(strict_types=1);

namespace Modules\Clients\Domain\Enums;

/**
 * CRM lifecycle state of a client record, independent of the soft-delete state.
 *
 * `Draft` is the row as first captured (matches the `clients.status` column
 * default); `Active` is a client currently being billed / worked with;
 * `Inactive` is a dormant relationship kept for history. Soft-deletion is a
 * separate axis handled by `deleted_at` and surfaces in exports as
 * `Active` / `Suspended` (BACKEND-PHP §8), never here.
 */
enum ClientStatus: string
{
    case Draft = 'DRAFT';
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';

    /**
     * Human-readable label for tables and the export "Lifecycle" column.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}
