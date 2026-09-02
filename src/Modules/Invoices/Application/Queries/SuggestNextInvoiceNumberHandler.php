<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Queries;

use Modules\Invoices\Application\Support\InvoiceTotalsCalculator;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;

final readonly class SuggestNextInvoiceNumberHandler
{
    public function __construct(private InvoiceRepositoryPort $invoices) {}

    /**
     * @return array{invoice_number: string, sequence: int, year: int}
     */
    public function handle(?int $year = null): array
    {
        $year ??= (int) now()->year;
        $sequence = $this->invoices->nextSequenceForYear($year);

        return [
            'invoice_number' => InvoiceTotalsCalculator::formatInvoiceNumber($sequence, $year),
            'sequence' => $sequence,
            'year' => $year,
        ];
    }
}
