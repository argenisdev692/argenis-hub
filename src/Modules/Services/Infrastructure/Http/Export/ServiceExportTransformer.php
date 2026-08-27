<?php

declare(strict_types=1);

namespace Modules\Services\Infrastructure\Http\Export;

use Modules\ActivityLog\Infrastructure\Http\Export\ActivityLogExportTransformer;
use Modules\Services\Infrastructure\Http\Controllers\AdminServiceController;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

/**
 * Maps a {@see ServiceEloquentModel} row to export columns, shared by the CSV,
 * Excel and PDF branches of {@see AdminServiceController::export}
 * so all three stay identical. The module ships only this row transform — the
 * writer / streamer / PDF renderer live behind the Shared `ExportPort`
 * (BACKEND-PHP §8), same split as {@see ActivityLogExportTransformer}.
 */
final readonly class ServiceExportTransformer
{
    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function headers(): array
    {
        return ['Name', 'Slug', 'Description', 'Status', 'Order', 'Created'];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function toRow(ServiceEloquentModel $service): array
    {
        return [
            'Name' => $service->name,
            'Slug' => $service->slug,
            'Description' => $service->description ?? '—',
            'Status' => self::status($service),
            'Order' => (string) $service->sort_order,
            'Created' => $service->created_at?->format('F j, Y H:i') ?? '—',
        ];
    }

    private static function status(ServiceEloquentModel $service): string
    {
        return match (true) {
            $service->deleted_at !== null => 'Deleted',
            $service->is_active => 'Active',
            default => 'Inactive',
        };
    }
}
