<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Authorization\Application\DTOs\RoleFilterData;
use Modules\Authorization\Infrastructure\Http\Export\RoleExportTransformer;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Role;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the filtered role list as CSV / Excel / PDF. Reuses the SAME
 * {@see RoleFilterData} + `scopeApplyFilters()` as the list query (DRY) and the
 * Shared {@see ExportPort}. `suspended` maps to `onlyTrashed()`, mirroring the
 * repository.
 */
final readonly class RoleExportController
{
    public function __construct(private ExportPort $export) {}

    public function __invoke(Request $request): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'csv');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $filters = RoleFilterData::validateAndCreate($request);

        $rows = Role::query()
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->with('permissions:id,name')
            ->orderBy('name')
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'roles.pdf',
                'exports.pdf.roles',
                [
                    'rows' => $rows->map(RoleExportTransformer::transformForPdf(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "roles.{$format}",
                ['Name', 'Guard', 'Permissions', 'Created', 'Status'],
                $rows->map(RoleExportTransformer::transformForTable(...)),
            ),
        };
    }
}
