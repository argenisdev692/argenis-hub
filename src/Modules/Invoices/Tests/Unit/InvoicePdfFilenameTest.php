<?php

declare(strict_types=1);

namespace Modules\Invoices\Tests\Unit;

use Illuminate\Support\Carbon;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\Invoices\Application\Support\InvoicePdfFilename;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class InvoicePdfFilenameTest extends TestCase
{
    #[DataProvider('filenameProvider')]
    public function test_builds_expected_download_filename(
        string $clientName,
        int $sequence,
        string $issueDate,
        string $expected,
    ): void {
        $client = new ClientEloquentModel(['client_name' => $clientName]);
        $invoice = new InvoiceEloquentModel([
            'sequence' => $sequence,
            'issue_date' => Carbon::parse($issueDate),
        ]);
        $invoice->setRelation('client', $client);

        $this->assertSame($expected, InvoicePdfFilename::forInvoice($invoice));
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: string, 3: string}>
     */
    public static function filenameProvider(): array
    {
        return [
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
        ];
    }
}
