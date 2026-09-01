<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Authorization\Application\DTOs\PermissionFilterData;
use Modules\Authorization\Infrastructure\Http\Export\PermissionExportTransformer;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Permission;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the filtered permission list as CSV / Excel / PDF. See
 * {@see RoleExportController} for the shared mechanics.
 */
final readonly class PermissionExportController
{
    public function __construct(private ExportPort $export) {}

    public function __invoke(Request $request): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'csv');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $filters = PermissionFilterData::validateAndCreate($request);

        $rows = Permission::query()
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->with('roles:id,name')
            ->orderBy('name')
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'permissions.pdf',
                'exports.pdf.permissions',
                [
                    'rows' => $rows->map(PermissionExportTransformer::transformForPdf(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "permissions.{$format}",
                ['Name', 'Guard', 'Roles', 'Created', 'Status'],
                $rows->map(PermissionExportTransformer::transformForTable(...)),
            ),
        };
    }
}
