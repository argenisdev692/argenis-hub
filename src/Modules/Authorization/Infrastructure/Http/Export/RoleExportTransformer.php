<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Http\Export;

use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Role;

/**
 * Maps a {@see Role} row to export columns so CSV, Excel and PDF stay consistent
 * with the on-screen list. The writer/streamer/PDF renderer live behind the Shared
 * `ExportPort` (BACKEND-PHP §8) — this module ships only the transformer.
 */
final readonly class RoleExportTransformer
{
    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function transformForTable(Role $role): array
    {
        return [
            'Name' => $role->name,
            'Guard' => $role->guard_name,
            'Permissions' => self::permissionSummary($role),
            // BACKEND-PHP §8 — exports use the human-readable "March 3, 2026".
            'Created' => $role->created_at?->format('F j, Y') ?? '—',
            'Status' => $role->deleted_at !== null ? 'Suspended' : 'Active',
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function transformForPdf(Role $role): array
    {
        return self::transformForTable($role);
    }

    /**
     * Flatten the eager-loaded permissions into a single comma-separated cell.
     * The `|>` chain reads as extract → sort → join.
     */
    private static function permissionSummary(Role $role): string
    {
        $names = $role->permissions->pluck('name')->all()
            |> (static fn (array $n): array => array_values(array_filter($n, is_string(...))))
            |> (static function (array $n): array {
                sort($n);

                return $n;
            });

        return $names === [] ? '—' : implode(', ', $names);
    }
}
