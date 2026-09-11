<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\DTOs;

use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class InvoiceFilterData extends SoftDeleteFilterData
{
    public function __construct(
        ?string $search = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        public ?int $year = null,
        public ?string $clientUuid = null,
        /**
         * The settlement axis, orthogonal to the soft-delete `$status` one: a
         * suspended invoice can perfectly well have been paid.
         *
         * A tri-state string rather than a `?bool` because the wire has to
         * distinguish "unpaid only" from "no opinion", and `is_paid=0` is
         * indistinguishable from an absent param once it has been through
         * `filter_var`. Null (the param omitted) means both.
         */
        public ?string $paymentStatus = null,
    ) {
        parent::__construct($search, $status, $dateFrom, $dateTo);
    }

    /**
     * The comments below are PUBLIC API DOCUMENTATION, not notes to the next
     * maintainer.
     *
     * Scramble harvests the comment directly above each rule as that query
     * parameter's `description` in `api.json`, and it wins over a
     * `#[QueryParameter(description: …)]` on the controller. Writing them here
     * is what keeps one copy of each filter's meaning instead of two that drift
     * — but it also means an internal aside ends up on the published spec, so
     * anything addressed to a maintainer belongs in the class docblock above,
     * never in this array.
     *
     * `status = all` is the only value that lifts the SoftDeletes scope (see
     * `InvoiceEloquentModel::scopeApplyFilters`); omitting the parameter stays
     * active-only, which is what keeps existing API consumers unaffected.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            ...self::baseRules(),
            // Soft-delete axis. `active` (and omitting this) hides suspended
            // invoices, `suspended` shows only those, `all` returns both side
            // by side.
            'status' => ['nullable', 'string', 'in:active,suspended,all'],
            // Restrict to one invoicing year. Invoice numbers are a per-year
            // sequence, so this is the natural way to page through a ledger.
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            // Restrict to the invoices billed to one client.
            'client_uuid' => ['nullable', 'uuid'],
            // Settlement axis, independent of `status`: a suspended invoice can
            // still have been paid. Omit for both paid and unpaid.
            'payment_status' => ['nullable', 'string', 'in:paid,unpaid'],
        ];
    }
}
