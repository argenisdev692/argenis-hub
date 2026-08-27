<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\ContactSupport\Application\Commands\BulkDeleteContactSupportHandler;
use Modules\ContactSupport\Application\Commands\BulkRestoreContactSupportHandler;
use Modules\ContactSupport\Application\Commands\CreateContactSupportHandler;
use Modules\ContactSupport\Application\Commands\DeleteContactSupportHandler;
use Modules\ContactSupport\Application\Commands\RestoreContactSupportHandler;
use Modules\ContactSupport\Application\Commands\UpdateContactSupportHandler;
use Modules\ContactSupport\Application\DTOs\ContactSupportFilterData;
use Modules\ContactSupport\Application\Queries\GetContactSupportHandler;
use Modules\ContactSupport\Application\Queries\ListContactSupportsHandler;
use Modules\ContactSupport\Infrastructure\Http\Export\ContactSupportExportTransformer;
use Modules\ContactSupport\Infrastructure\Http\Requests\BulkDeleteContactSupportRequest;
use Modules\ContactSupport\Infrastructure\Http\Requests\BulkRestoreContactSupportRequest;
use Modules\ContactSupport\Infrastructure\Http\Requests\StoreContactSupportRequest;
use Modules\ContactSupport\Infrastructure\Http\Requests\UpdateContactSupportRequest;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The support-inbox admin surface: one Inertia page (`page()`) plus the JSON
 * data endpoints it consumes under `/data/admin/contact-supports` (Controller
 * Fusion Rule — one entity, one permission set). Each method is guarded by its
 * own permission at the route level (`web.php`), not re-checked here.
 */
final readonly class AdminContactSupportController
{
    public function __construct(
        private ListContactSupportsHandler $listContactSupports,
        private GetContactSupportHandler $getContactSupport,
        private CreateContactSupportHandler $createContactSupport,
        private UpdateContactSupportHandler $updateContactSupport,
        private DeleteContactSupportHandler $deleteContactSupport,
        private RestoreContactSupportHandler $restoreContactSupport,
        private BulkDeleteContactSupportHandler $bulkDeleteContactSupport,
        private BulkRestoreContactSupportHandler $bulkRestoreContactSupport,
        private ExportPort $export,
    ) {}

    /**
     * The table fetches its own rows via Pinia Colada against `index()`, so the
     * page itself carries no server-rendered props.
     */
    public function page(): InertiaResponse
    {
        return Inertia::render('contact-support/Index');
    }

    public function index(ContactSupportFilterData $filters): JsonResponse
    {
        return response()->json($this->listContactSupports->handle($filters));
    }

    /**
     * Streams the filtered inbox as CSV / Excel / PDF. Reuses the SAME
     * `ContactSupportEloquentModel::applyFilters()` the list query runs (DRY)
     * minus pagination. The date-range invariant (`date_from` ≤ `date_to`) is
     * enforced by {@see ContactSupportFilterData}; an unknown `format` is a 422.
     */
    public function export(Request $request, ContactSupportFilterData $filters): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $rows = ContactSupportEloquentModel::query()
            ->applyFilters($filters)
            ->select([
                'id', 'uuid', 'first_name', 'last_name', 'email', 'phone', 'subject',
                'readed', 'sms_consent', 'is_spam', 'created_at', 'deleted_at',
            ])
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'contact-supports.pdf',
                'exports.pdf.contact-supports',
                [
                    'rows' => $rows->map(ContactSupportExportTransformer::toRow(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "contact-supports.{$format}",
                ContactSupportExportTransformer::headers(),
                $rows->map(ContactSupportExportTransformer::toRow(...)),
            ),
        };
    }

    public function store(StoreContactSupportRequest $request): JsonResponse
    {
        $support = $this->createContactSupport->handle(
            $request->validated(),
            (int) $request->user()?->getAuthIdentifier(),
        );

        return response()->json($support, 201);
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json($this->getContactSupport->handle($uuid));
    }

    public function update(string $uuid, UpdateContactSupportRequest $request): JsonResponse
    {
        return response()->json($this->updateContactSupport->handle($uuid, $request->validated()));
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->deleteContactSupport->handle($uuid);

        return response()->json(status: 204);
    }

    public function restore(string $uuid): JsonResponse
    {
        return response()->json($this->restoreContactSupport->handle($uuid));
    }

    public function bulkDelete(BulkDeleteContactSupportRequest $request): JsonResponse
    {
        $deleted = $this->bulkDeleteContactSupport->handle($request->validated('uuids'));

        return response()->json(['deleted' => $deleted]);
    }

    public function bulkRestore(BulkRestoreContactSupportRequest $request): JsonResponse
    {
        $restored = $this->bulkRestoreContactSupport->handle($request->validated('uuids'));

        return response()->json(['restored' => $restored]);
    }
}
