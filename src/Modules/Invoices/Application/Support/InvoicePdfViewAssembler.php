<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Support;

use Illuminate\Support\Carbon;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Shared\Domain\Enums\BillingUnit;
use Shared\Domain\Enums\Currency;
use Shared\Support\EuSchengenCountries;

/**
 * Locale-specific labels and formatting for the single-invoice PDF (dompdf).
 */
final class InvoicePdfViewAssembler
{
    /**
     * @param  array{
     *     country?: string|null,
     *     country_code?: string|null,
     *     ...
     * }  $company
     * @return array{
     *     html_lang: string,
     *     locale: string,
     *     labels: array<string, string>,
     *     issue_date: string,
     *     due_date: string,
     *     payment_date: string,
     *     client_tax_id_label: string,
     *     notes_body: string|null,
     *     additional_notes: string|null,
     *     currency_symbol: string,
     *     tax_exempt: bool,
     *     unit_labels: array<string, string>,
     *     unit_price_heading: string,
     *     payment_method_label: string|null,
     *     payment_details: list<array{label: string, value: string}>,
     *     money: InvoiceMoneyFormatter
     * }
     */
    public function assemble(InvoiceEloquentModel $invoice, array $company): array
    {
        $invoice->loadMissing([
            'client:id,uuid,client_name,email,phone,tax_id,nif,address,country,country_code',
            'items',
        ]);

        $client = $invoice->client;
        $clientCode = strtoupper((string) ($client?->country_code ?? ''));
        $locale = self::resolveDocumentLocale($clientCode);
        $labels = self::labelsFor($locale);

        $issuerName = trim((string) ($company['country'] ?? ''));
        if ($issuerName === '') {
            $issuerName = 'Portugal';
        }

        $fiscalNotice = InvoiceCrossBorderVatNotice::forExemptInvoice(
            $invoice,
            $issuerName,
            $client?->country,
            $client?->country_code,
        );

        $storedNotes = trim((string) ($invoice->notes ?? ''));
        $notesBody = $storedNotes !== '' ? $storedNotes : trim((string) $fiscalNotice);
        $notesBody = $notesBody !== '' ? $notesBody : null;

        $additionalNotes = trim((string) ($invoice->additional_notes ?? ''));
        $additionalNotes = $additionalNotes !== '' ? $additionalNotes : null;

        $currency = strtoupper((string) ($invoice->currency ?: 'USD'));
        $symbol = self::currencySymbol($currency);
        $dominantUnit = self::dominantUnit($invoice);

        return [
            'html_lang' => match ($locale) {
                'pt' => 'pt',
                'es' => 'es',
                default => 'en',
            },
            'locale' => $locale,
            'labels' => $labels,
            'unit_labels' => self::unitLabelsFor($locale),
            // "Precio/Hora" when the invoice bills time, "Precio unitario"
            // otherwise — the Imagina training invoices depend on this.
            'unit_price_heading' => self::unitPriceHeading($dominantUnit, $labels),
            'payment_method_label' => self::paymentMethodLabel($invoice, $labels),
            'payment_details' => self::paymentDetails($invoice, $company, $labels),
            'money' => new InvoiceMoneyFormatter($locale, $currency, $symbol),
            'issue_date' => self::formatDate($invoice->issue_date, $locale),
            'due_date' => self::formatDate($invoice->due_date, $locale),
            'payment_date' => $invoice->payment_date !== null
                ? self::formatDate($invoice->payment_date, $locale)
                : '',
            'client_tax_id_label' => self::clientTaxIdLabel($clientCode, $locale),
            'notes_body' => $notesBody,
            'additional_notes' => $additionalNotes,
            'currency_symbol' => $symbol,
            // One definition of "exempt" for the totals row AND the fiscal notice.
            'tax_exempt' => InvoiceCrossBorderVatNotice::isTaxExempt($invoice),
        ];
    }

    /**
     * The unit that decides the unit-price column header. A mixed invoice
     * (a course line plus a web-development line) falls back to the neutral
     * "unit price" heading rather than mislabelling either row.
     */
    private static function dominantUnit(InvoiceEloquentModel $invoice): ?BillingUnit
    {
        $units = [];

        foreach ($invoice->items as $item) {
            $units[$item->unit->value] = $item->unit;
        }

        return count($units) === 1 ? reset($units) : null;
    }

    /**
     * @param  array<string, string>  $labels
     */
    private static function unitPriceHeading(?BillingUnit $unit, array $labels): string
    {
        return match ($unit) {
            BillingUnit::Hour => $labels['price_per_hour'],
            BillingUnit::Session => $labels['price_per_session'],
            BillingUnit::Day => $labels['price_per_day'],
            BillingUnit::Month => $labels['price_per_month'],
            default => $labels['unit_price'],
        };
    }

    /**
     * @return array<string, string>
     */
    private static function unitLabelsFor(string $locale): array
    {
        return match ($locale) {
            'pt' => [
                BillingUnit::Unit->value => '',
                BillingUnit::Hour->value => 'horas',
                BillingUnit::Session->value => 'sessões',
                BillingUnit::Day->value => 'dias',
                BillingUnit::Month->value => 'meses',
            ],
            'es' => [
                BillingUnit::Unit->value => '',
                BillingUnit::Hour->value => 'horas',
                BillingUnit::Session->value => 'sesiones',
                BillingUnit::Day->value => 'días',
                BillingUnit::Month->value => 'meses',
            ],
            default => [
                BillingUnit::Unit->value => '',
                BillingUnit::Hour->value => 'hours',
                BillingUnit::Session->value => 'sessions',
                BillingUnit::Day->value => 'days',
                BillingUnit::Month->value => 'months',
            ],
        };
    }

    /**
     * @param  array<string, string>  $labels
     */
    private static function paymentMethodLabel(InvoiceEloquentModel $invoice, array $labels): ?string
    {
        $method = $invoice->payment_method;
        if (! $method instanceof PaymentMethod) {
            return null;
        }

        return $labels[$method->labelKey()] ?? $method->value;
    }

    /**
     * Settlement details for the document: the snapshot taken when the invoice
     * was issued, falling back to the legacy single company bank block for
     * invoices created before payment accounts existed.
     *
     * @param  array<string, mixed>  $company
     * @param  array<string, string>  $labels
     * @return list<array{label: string, value: string}>
     */
    private static function paymentDetails(InvoiceEloquentModel $invoice, array $company, array $labels): array
    {
        $snapshot = $invoice->payment_details_json;

        if (! is_array($snapshot) || $snapshot === []) {
            $snapshot = [
                'beneficiary' => $company['bank_beneficiary'] ?? null,
                'iban' => $company['bank_iban'] ?? null,
                'bic' => $company['bank_bic'] ?? null,
                'bank_name' => $company['bank_name'] ?? null,
            ];
        }

        $rows = [
            ['label' => $labels['beneficiary'], 'value' => $snapshot['beneficiary'] ?? null],
            ['label' => 'IBAN', 'value' => $snapshot['iban'] ?? null],
            ['label' => 'BIC/SWIFT', 'value' => $snapshot['bic'] ?? null],
            ['label' => $labels['bank'], 'value' => $snapshot['bank_name'] ?? null],
            ['label' => $labels['account_number'], 'value' => $snapshot['account_number'] ?? null],
            ['label' => $labels['routing_number'], 'value' => $snapshot['routing_number'] ?? null],
            ['label' => $labels['email'], 'value' => $snapshot['holder_email'] ?? null],
            ['label' => $labels['phone'], 'value' => $snapshot['holder_phone'] ?? null],
            ['label' => $labels['instructions'], 'value' => $snapshot['instructions'] ?? null],
        ];

        $details = [];

        foreach ($rows as $row) {
            $value = trim((string) ($row['value'] ?? ''));
            if ($value !== '') {
                $details[] = ['label' => $row['label'], 'value' => $value];
            }
        }

        return $details;
    }

    private static function resolveDocumentLocale(string $clientCountryCode): string
    {
        if ($clientCountryCode === 'PT') {
            return 'pt';
        }

        if ($clientCountryCode === 'ES') {
            return 'es';
        }

        if ($clientCountryCode === 'US') {
            return 'en';
        }

        if (EuSchengenCountries::includes($clientCountryCode)) {
            return 'es';
        }

        if ($clientCountryCode === '') {
            return 'en';
        }

        return 'en';
    }

    /**
     * @return array<string, string>
     */
    private static function labelsFor(string $locale): array
    {
        return match ($locale) {
            'pt' => [
                'document_title' => 'FATURA',
                'invoice_no' => 'N.º da fatura',
                'issue_date' => 'Data de emissão',
                'due_date' => 'Data de vencimento',
                'from' => 'Emitente',
                'bill_to' => 'Cliente',
                'concept' => 'Descrição',
                'quantity' => 'Qtd.',
                'unit_price' => 'Preço unit.',
                'amount' => 'Montante',
                'subtotal' => 'Subtotal',
                'exempt' => 'Exento',
                'total_due' => 'Total a pagar',
                'total_paid' => 'Total pago',
                'fiscal_heading' => 'Informação fiscal',
                'notes_heading' => 'Notas',
                'additional_notes_heading' => 'Notas adicionais',
                'payment_received' => 'Pagamento recebido',
                'payment_method' => 'Método de pagamento',
                'transfer_number' => 'N.º de transferência',
                'payment_date' => 'Data de pagamento',
                'amount_received' => 'Montante recebido',
                'bank_heading' => 'Dados bancários',
                'beneficiary' => 'Beneficiário',
                'bank' => 'Banco',
                'footer_thanks' => 'Obrigado pela confiança.',
                'status_paid' => 'Paga',
                'status_pending' => 'Pendente',
                'email' => 'Email',
                'phone' => 'Tel.',
                'price_per_hour' => 'Preço/Hora',
                'price_per_session' => 'Preço/Sessão',
                'price_per_day' => 'Preço/Dia',
                'price_per_month' => 'Preço/Mês',
                'account_number' => 'N.º de conta',
                'routing_number' => 'Routing number',
                'instructions' => 'Instruções',
                'payment_method_remitly' => 'Remitly (Transferência Internacional)',
                'payment_method_bank_transfer' => 'Transferência bancária',
                'payment_method_wise' => 'Wise (Transferência Internacional)',
                'payment_method_paypal' => 'PayPal',
                'payment_method_stripe' => 'Stripe',
                'payment_method_cash' => 'Numerário',
                'payment_method_other' => 'Outro',
            ],
            'es' => [
                'document_title' => 'FACTURA',
                'invoice_no' => 'N.º de factura',
                'issue_date' => 'Fecha de emisión',
                'due_date' => 'Fecha de vencimiento',
                'from' => 'Proveedor',
                'bill_to' => 'Cliente',
                'concept' => 'Concepto',
                'quantity' => 'Cantidad',
                'unit_price' => 'Precio unitario',
                'amount' => 'Importe',
                'subtotal' => 'Subtotal',
                'exempt' => 'Exento',
                'total_due' => 'Total a pagar',
                'total_paid' => 'Total pagado',
                'fiscal_heading' => 'Información fiscal',
                'notes_heading' => 'Notas',
                'additional_notes_heading' => 'Notas adicionales',
                'payment_received' => 'Pago recibido',
                'payment_method' => 'Método de pago',
                'transfer_number' => 'N.º de transferencia',
                'payment_date' => 'Fecha de pago',
                'amount_received' => 'Importe recibido',
                'bank_heading' => 'Datos bancarios',
                'beneficiary' => 'Beneficiario',
                'bank' => 'Banco',
                'footer_thanks' => 'Gracias por su confianza.',
                'status_paid' => 'Pagada',
                'status_pending' => 'Pendiente',
                'email' => 'Email',
                'phone' => 'Tel.',
                'price_per_hour' => 'Precio/Hora',
                'price_per_session' => 'Precio/Sesión',
                'price_per_day' => 'Precio/Día',
                'price_per_month' => 'Precio/Mes',
                'account_number' => 'N.º de cuenta',
                'routing_number' => 'Routing number',
                'instructions' => 'Instrucciones',
                'payment_method_remitly' => 'Remitly (Transferencia Internacional)',
                'payment_method_bank_transfer' => 'Transferencia bancaria',
                'payment_method_wise' => 'Wise (Transferencia Internacional)',
                'payment_method_paypal' => 'PayPal',
                'payment_method_stripe' => 'Stripe',
                'payment_method_cash' => 'Efectivo',
                'payment_method_other' => 'Otro',
            ],
            default => [
                'document_title' => 'INVOICE',
                'invoice_no' => 'Invoice no.',
                'issue_date' => 'Issue date',
                'due_date' => 'Due date',
                'from' => 'From',
                'bill_to' => 'Bill to',
                'concept' => 'Description',
                'quantity' => 'Qty',
                'unit_price' => 'Unit price',
                'amount' => 'Amount',
                'subtotal' => 'Subtotal',
                'exempt' => 'Exempt',
                'total_due' => 'Total due',
                'total_paid' => 'Total paid',
                'fiscal_heading' => 'Tax information',
                'notes_heading' => 'Notes',
                'additional_notes_heading' => 'Additional notes',
                'payment_received' => 'Payment received',
                'payment_method' => 'Payment method',
                'transfer_number' => 'Transfer number',
                'payment_date' => 'Payment date',
                'amount_received' => 'Amount received',
                'bank_heading' => 'Bank details',
                'beneficiary' => 'Beneficiary',
                'bank' => 'Bank',
                'footer_thanks' => 'Thank you for your business.',
                'status_paid' => 'Paid',
                'status_pending' => 'Pending',
                'email' => 'Email',
                'phone' => 'Phone',
                'price_per_hour' => 'Price/Hour',
                'price_per_session' => 'Price/Session',
                'price_per_day' => 'Price/Day',
                'price_per_month' => 'Price/Month',
                'account_number' => 'Account number',
                'routing_number' => 'Routing number',
                'instructions' => 'Instructions',
                'payment_method_remitly' => 'Remitly (International Transfer)',
                'payment_method_bank_transfer' => 'Bank transfer',
                'payment_method_wise' => 'Wise (International Transfer)',
                'payment_method_paypal' => 'PayPal',
                'payment_method_stripe' => 'Stripe',
                'payment_method_cash' => 'Cash',
                'payment_method_other' => 'Other',
            ],
        };
    }

    private static function clientTaxIdLabel(string $clientCode, string $locale): string
    {
        if ($clientCode === 'US') {
            return 'EIN / Tax ID';
        }

        if ($clientCode === 'PT') {
            return match ($locale) {
                'pt' => 'NIF',
                'es' => 'NIF',
                default => 'NIF / Tax ID',
            };
        }

        if ($clientCode === 'ES' || EuSchengenCountries::includes($clientCode)) {
            return match ($locale) {
                'es' => 'NIF / CIF',
                'pt' => 'NIF / CIF',
                default => 'Tax ID / VAT no.',
            };
        }

        return 'Tax ID';
    }

    private static function formatDate(?\DateTimeInterface $date, string $locale): string
    {
        if ($date === null) {
            return '';
        }

        $carbon = Carbon::instance($date);

        return match ($locale) {
            'pt' => $carbon->locale('pt')->translatedFormat('j \\d\\e F \\d\\e Y'),
            'es' => $carbon->locale('es')->translatedFormat('j \\d\\e F \\d\\e Y'),
            default => $carbon->locale('en')->translatedFormat('F j, Y'),
        };
    }

    /**
     * A legacy row in a currency outside {@see Currency} prints its ISO code
     * rather than borrowing another currency's symbol.
     */
    private static function currencySymbol(string $currency): string
    {
        return Currency::tryFrom(strtoupper($currency))?->symbol() ?? strtoupper($currency);
    }
}
