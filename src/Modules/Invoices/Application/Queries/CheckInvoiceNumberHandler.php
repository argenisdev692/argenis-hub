<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Queries;

use Modules\Invoices\Application\Support\InvoiceTotalsCalculator;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;

/**
 * Realtime invoice-number conflict check for the create/edit form.
 * Accepts `014/2026` or bare `014` (expanded to current year).
 */
final readonly class CheckInvoiceNumberHandler
{
    public function __construct(private InvoiceRepositoryPort $invoices) {}

    /**
     * @return array{
     *     available: bool,
     *     invoice_number: string,
     *     invoice: array{uuid: string, invoice_number: string, client_name: string, is_suspended: bool}|null
     * }
     */
    public function handle(string $rawNumber, ?string $exceptUuid = null, ?int $year = null): array
    {
        $normalized = $this->normalize($rawNumber, $year);
        $parsed = InvoiceTotalsCalculator::parseInvoiceNumber($normalized);
        $conflict = $this->invoices->findNumberConflict(
            $normalized,
            $parsed['year'],
            $parsed['sequence'],
            $exceptUuid,
        );

        return [
            'available' => $conflict === null,
            'invoice_number' => $normalized,
            'invoice' => $conflict,
        ];
    }

    private function normalize(string $rawNumber, ?int $year): string
    {
        $trimmed = trim($rawNumber);

        if (preg_match('/^\d{1,6}$/', $trimmed) === 1) {
            return InvoiceTotalsCalculator::formatInvoiceNumber(
                (int) $trimmed,
                $year ?? (int) now()->year,
            );
        }

        if (preg_match('/^(\d{1,6})\/(\d{4})$/', $trimmed, $matches) === 1) {
            return InvoiceTotalsCalculator::formatInvoiceNumber((int) $matches[1], (int) $matches[2]);
        }

        return $trimmed;
    }
}
