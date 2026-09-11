<?php

declare(strict_types=1);

use Modules\Invoices\Application\DTOs\InvoiceFilterData;
use Modules\Invoices\Application\Support\InvoiceFilterSummary;

/**
 * The line a printed invoice report carries under its heading.
 *
 * Worth its own tests because it is the only part of the export a reader sees
 * *before* the rows: get it wrong and a report of unpaid invoices reads as a
 * report of every invoice, which is the failure the filter was added to avoid.
 */
it('says so plainly when nothing narrowed the export', function (): void {
    expect(InvoiceFilterSummary::describe(new InvoiceFilterData))
        ->toBe('All issued invoices.');
});

it('names the settlement filter first', function (): void {
    expect(InvoiceFilterSummary::describe(new InvoiceFilterData(paymentStatus: 'unpaid')))
        ->toBe('Unpaid invoices only.');

    expect(InvoiceFilterSummary::describe(new InvoiceFilterData(paymentStatus: 'paid')))
        ->toBe('Paid invoices only.');
});

it('stays silent about a status that changes nothing', function (): void {
    // `active` and an omitted status return the same rows, so neither earns a
    // clause — only `suspended` and `all` change what the reader is holding.
    expect(InvoiceFilterSummary::describe(new InvoiceFilterData(status: 'active')))
        ->toBe('All issued invoices.');

    expect(InvoiceFilterSummary::describe(new InvoiceFilterData(status: 'all')))
        ->toBe('Including suspended.');

    expect(InvoiceFilterSummary::describe(new InvoiceFilterData(status: 'suspended')))
        ->toBe('Suspended only.');
});

it('renders an ISO date window in the same words as the rows', function (): void {
    expect(InvoiceFilterSummary::describe(
        new InvoiceFilterData(dateFrom: '2026-03-01', dateTo: '2026-03-31'),
    ))->toBe('Issued March 1, 2026 – March 31, 2026.');

    expect(InvoiceFilterSummary::describe(new InvoiceFilterData(dateFrom: '2026-03-01')))
        ->toBe('Issued from March 1, 2026.');

    expect(InvoiceFilterSummary::describe(new InvoiceFilterData(dateTo: '2026-03-31')))
        ->toBe('Issued up to March 31, 2026.');
});

it('joins every active facet in one line', function (): void {
    $filters = new InvoiceFilterData(
        search: 'Acme',
        status: 'all',
        dateFrom: '2026-03-01',
        dateTo: '2026-03-31',
        year: 2026,
        paymentStatus: 'unpaid',
    );

    expect(InvoiceFilterSummary::describe($filters))->toBe(
        'Unpaid invoices only · including suspended · year 2026 · '
        .'issued March 1, 2026 – March 31, 2026 · matching “Acme”.',
    );
});

it('drops an unparseable date rather than failing the export', function (): void {
    // The rows are already correct by the time this runs — a bad bound is not
    // worth turning a rendered report into a 500.
    expect(InvoiceFilterSummary::describe(
        new InvoiceFilterData(dateFrom: 'not-a-date', paymentStatus: 'paid'),
    ))->toBe('Paid invoices only.');
});

it('ignores a blank search term', function (): void {
    expect(InvoiceFilterSummary::describe(new InvoiceFilterData(search: '   ')))
        ->toBe('All issued invoices.');
});
