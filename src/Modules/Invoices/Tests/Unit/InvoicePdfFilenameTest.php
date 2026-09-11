<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\Invoices\Application\Support\InvoicePdfFilename;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;

it('builds the expected download filename', function (string $clientName, int $sequence, string $issueDate, string $expected): void {
    $invoice = new InvoiceEloquentModel([
        'sequence' => $sequence,
        'issue_date' => Carbon::parse($issueDate),
    ]);
    $invoice->setRelation('client', new ClientEloquentModel(['client_name' => $clientName]));

    expect(InvoicePdfFilename::forInvoice($invoice))->toBe($expected);
})->with([
    'title case with acronym' => [
        'Aquashield Restoration LLC',
        15,
        '2026-08-01',
        'Invoice-Aquashield-Restoration-LLC-015-01-08-2026.pdf',
    ],
    'lowercase input' => [
        'aquashield restoration llc',
        15,
        '2026-08-01',
        'Invoice-Aquashield-Restoration-LLC-015-01-08-2026.pdf',
    ],
    'mixed acronym token' => [
        'aquashield restoration LLC',
        4,
        '2026-01-08',
        'Invoice-Aquashield-Restoration-LLC-004-08-01-2026.pdf',
    ],
]);
