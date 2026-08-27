<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Http\Controllers;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Backups\Application\Commands\BulkDeleteBackupHandler;
use Modules\Backups\Application\Commands\DeleteBackupHandler;
use Modules\Backups\Application\Commands\RunDatabaseBackupHandler;
use Modules\Backups\Application\DTOs\BackupFilterData;
use Modules\Backups\Application\Queries\GetBackupHandler;
use Modules\Backups\Application\Queries\ListBackupsHandler;
use Modules\Backups\Infrastructure\Http\Export\BackupExportTransformer;
use Modules\Backups\Infrastructure\Http\Requests\BulkDeleteBackupRequest;
use Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The backups panel: one Inertia page (`page()`) plus the JSON data endpoints it
 * consumes under `/data/admin/backups` (Controller Fusion Rule). Each method is
 * guarded by its own permission at the route level (`web.php`), not re-checked
 * here. A backup archive is immutable, so there is no store/update of a row from
 * the UI — only `store()` to trigger a fresh run, and delete / bulk-delete /
 * download / export over what already exists.
 */
final readonly class AdminBackupController
{
    public function __construct(
        private ListBackupsHandler $listBackups,
        private GetBackupHandler $getBackup,
        private RunDatabaseBackupHandler $runBackup,
        private DeleteBackupHandler $deleteBackup,
        private BulkDeleteBackupHandler $bulkDeleteBackup,
        private ExportPort $export,
        private FilesystemFactory $filesystem,
    ) {}

    public function page(): InertiaResponse
    {
        return Inertia::render('backups/Index');
    }

    public function index(BackupFilterData $filters): JsonResponse
    {
        return response()->json($this->listBackups->handle($filters));
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json($this->getBackup->handle($uuid));
    }

    /**
     * Queues an on-demand database backup. `202 Accepted` — the archive is
     * produced off the request cycle and appears in the list once the job runs.
     */
    public function store(Request $request): JsonResponse
    {
        $this->runBackup->handle($request->user());

        return response()->json(['status' => 'queued'], 202);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->deleteBackup->handle($uuid);

        return response()->json(status: 204);
    }

    public function bulkDelete(BulkDeleteBackupRequest $request): JsonResponse
    {
        $deleted = $this->bulkDeleteBackup->handle($request->validated('uuids'));

        return response()->json(['deleted' => $deleted]);
    }

    public function download(string $uuid): StreamedResponse
    {
        $backup = BackupEloquentModel::query()->where('uuid', $uuid)->firstOrFail();

        $disk = $this->filesystem->disk($backup->disk);

        abort_if($backup->path === null || ! $disk->exists($backup->path), 404);

        return $disk->download($backup->path, $backup->filename);
    }

    /**
     * Streams the filtered index as CSV / Excel / PDF through the Shared
     * `ExportPort`, reusing the SAME `BackupEloquentModel::applyFilters()` as the
     * list (DRY) minus pagination. An unknown `format` is a 422; the date-range
     * invariant is enforced by {@see BackupFilterData}.
     */
    public function export(Request $request, BackupFilterData $filters): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $rows = BackupEloquentModel::query()
            ->applyFilters($filters)
            ->select(['id', 'uuid', 'disk', 'path', 'filename', 'size_bytes', 'status', 'connection', 'error', 'started_at', 'finished_at', 'created_at'])
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'backups.pdf',
                'exports.pdf.backups',
                [
                    'rows' => $rows->map(BackupExportTransformer::toRow(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "backups.{$format}",
                BackupExportTransformer::headers(),
                $rows->map(BackupExportTransformer::toRow(...)),
            ),
        };
    }
}
