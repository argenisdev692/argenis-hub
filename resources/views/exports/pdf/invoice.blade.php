<!DOCTYPE html>
<html lang="{{ $pdf['html_lang'] }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $pdf['labels']['document_title'] }} {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #333333;
            margin: 0;
            padding: 0;
            line-height: 1.45;
        }
        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .header td { vertical-align: top; }
        .header-logo { width: 130px; }
        .header-logo img {
            max-width: 120px;
            max-height: 52px;
        }
        .header-right { text-align: right; }
        h1 {
            font-size: 24px;
            color: #2c3e50;
            margin: 0 0 8px 0;
            letter-spacing: 0.4px;
        }
        h1.title-pending { color: #c62828; }
        h1.title-paid { color: #2e7d32; }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .status-badge--pending {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        .status-badge--paid {
            background: #e8f5e9;
            color: #1b5e20;
            border: 1px solid #a5d6a7;
        }
        .meta-strip {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            background: #f8f9fa;
        }
        .meta-strip td {
            padding: 10px 14px;
            vertical-align: top;
            border-right: 1px solid #e8e8e8;
        }
        .meta-strip td:last-child { border-right: none; }
        .meta-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #666;
            margin-bottom: 4px;
        }
        .meta-value {
            font-size: 12px;
            font-weight: bold;
            color: #2c3e50;
        }
        .meta-value--number { color: #c62828; }
        .parties {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px 0;
            margin-bottom: 22px;
        }
        .parties td {
            width: 50%;
            vertical-align: top;
            padding: 14px 16px;
            border: 1px solid #dde3ea;
            background: #ffffff;
        }
        .party-title {
            font-size: 9px;
            font-weight: bold;
            color: #5c6b7a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #e8ecef;
        }
        .party-name {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #2c3e50;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .party-line { margin: 3px 0; font-size: 11px; color: #444; line-height: 1.45; }
        .party-line strong {
            font-weight: bold;
            color: #2c3e50;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        table.items thead th {
            background: #2c3e50;
            color: #ffffff;
            font-size: 10px;
            text-align: left;
            padding: 9px 8px;
        }
        table.items thead th.num { text-align: right; }
        table.items tbody td {
            padding: 11px 8px;
            border-bottom: 1px solid #e8e8e8;
            vertical-align: top;
        }
        table.items tbody td.num { text-align: right; white-space: nowrap; }
        .item-title { font-weight: bold; font-size: 11px; }
        .item-desc { font-size: 10px; color: #555; margin-top: 3px; }
        .totals {
            width: 300px;
            margin-left: auto;
            margin-bottom: 20px;
        }
        .totals table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 5px 0; font-size: 12px; }
        .totals td.lbl { text-align: right; padding-right: 14px; color: #555; }
        .totals td.val { text-align: right; font-weight: bold; }
        .totals .grand td {
            padding-top: 10px;
            font-size: 14px;
            color: #2c3e50;
            border-top: 2px solid #2c3e50;
        }
        .notice-box {
            border: 1px solid #cfd8dc;
            background: #f5f7f8;
            padding: 12px 14px;
            margin-bottom: 14px;
        }
        .notice-box h3 {
            font-size: 11px;
            margin: 0 0 8px 0;
            color: #2c3e50;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .notice-box p {
            margin: 0;
            font-size: 10px;
            color: #444;
            white-space: pre-line;
        }
        .bank {
            border: 1px solid #c8e6c9;
            background: #f1f8e9;
            padding: 12px 14px;
            margin-bottom: 16px;
        }
        .bank h3 {
            margin: 0 0 8px 0;
            font-size: 12px;
            color: #2e7d32;
        }
        .bank p { margin: 2px 0; font-size: 11px; }
        .pago-recibido {
            border: 1px solid #2e7d32;
            background: #e8f5e9;
            padding: 12px 14px;
            margin-bottom: 16px;
        }
        .pago-recibido h3 {
            margin: 0 0 8px 0;
            font-size: 12px;
            color: #1b5e20;
        }
        .pago-recibido p { margin: 3px 0; font-size: 11px; }
        .footer {
            text-align: center;
            color: #666666;
            font-size: 10px;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #e8e8e8;
        }
    </style>
</head>
<body>
    @php
        $labels = $pdf['labels'];
        $currency = strtoupper($invoice->currency ?: 'USD');
        $symbol = $pdf['currency_symbol'];
        $fmt = static fn ($n) => $symbol.number_format((float) $n, 2).' '.$currency;
        $providerName = $company['legal_name'] ?: $company['name'];
        $providerNameUpper = mb_strtoupper((string) $providerName, 'UTF-8');
        $client = $invoice->client;
        $clientNameUpper = mb_strtoupper((string) ($client?->client_name ?? ''), 'UTF-8');
        $clientTaxId = \Modules\Invoices\Application\Support\InvoiceClientBilling::taxIdForClient($client);
        $statusLabel = $invoice->is_paid ? $labels['status_paid'] : $labels['status_pending'];
    @endphp

    <table class="header">
        <tr>
            <td class="header-logo">
                @if (! empty($company['invoice_logo_data_uri']))
                    <img src="{{ $company['invoice_logo_data_uri'] }}" alt="{{ $company['name'] }}" />
                @endif
            </td>
            <td class="header-right">
                <h1 class="{{ $invoice->is_paid ? 'title-paid' : 'title-pending' }}">{{ $labels['document_title'] }}</h1>
                <div class="status-badge {{ $invoice->is_paid ? 'status-badge--paid' : 'status-badge--pending' }}">
                    {{ $statusLabel }}
                </div>
            </td>
        </tr>
    </table>

    <table class="meta-strip">
        <tr>
            <td>
                <div class="meta-label">{{ $labels['invoice_no'] }}</div>
                <div class="meta-value meta-value--number">{{ $invoice->invoice_number }}</div>
            </td>
            <td>
                <div class="meta-label">{{ $labels['issue_date'] }}</div>
                <div class="meta-value">{{ $pdf['issue_date'] }}</div>
            </td>
            <td>
                <div class="meta-label">{{ $labels['due_date'] }}</div>
                <div class="meta-value">{{ $pdf['due_date'] }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="party-title">{{ $labels['from'] }}</div>
                <div class="party-name">{{ $providerNameUpper }}</div>
                @if (! empty($company['nif_nipc']))
                    <div class="party-line"><strong>NIF/NIPC:</strong> {{ $company['nif_nipc'] }}</div>
                @endif
                @if (! empty($company['nie']))
                    <div class="party-line"><strong>NIE:</strong> {{ $company['nie'] }}</div>
                @endif
                @if (! empty($company['address']))
                    <div class="party-line">{{ $company['address'] }}</div>
                @endif
                @if (! empty($company['email']))
                    <div class="party-line"><strong>{{ $labels['email'] }}:</strong> {{ $company['email'] }}</div>
                @endif
                @if (! empty($company['phone']))
                    <div class="party-line"><strong>{{ $labels['phone'] }}:</strong> {{ $company['phone'] }}</div>
                @endif
            </td>
            <td>
                <div class="party-title">{{ $labels['bill_to'] }}</div>
                @if ($client)
                    <div class="party-name">{{ $clientNameUpper }}</div>
                    @if ($clientTaxId)
                        <div class="party-line"><strong>{{ $pdf['client_tax_id_label'] }}:</strong> {{ $clientTaxId }}</div>
                    @endif
                    @if ($client->address)
                        <div class="party-line">{{ $client->address }}</div>
                    @endif
                    @if ($client->email)
                        <div class="party-line"><strong>{{ $labels['email'] }}:</strong> {{ $client->email }}</div>
                    @endif
                    @if ($client->phone)
                        <div class="party-line"><strong>{{ $labels['phone'] }}:</strong> {{ $client->phone }}</div>
                    @endif
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>{{ $labels['concept'] }}</th>
                <th class="num">{{ $labels['quantity'] }}</th>
                <th class="num">{{ $labels['unit_price'] }}</th>
                <th class="num">{{ $labels['amount'] }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>
                        <div class="item-title">{{ $item->title }}</div>
                        @if ($item->description)
                            <div class="item-desc">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td class="num">{{ number_format((float) $item->quantity, 0) }}</td>
                    <td class="num">{{ $fmt($item->unit_price) }}</td>
                    <td class="num">{{ $fmt($item->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td class="lbl">{{ $labels['subtotal'] }}:</td>
                <td class="val">{{ $fmt($invoice->subtotal) }}</td>
            </tr>
            <tr>
                <td class="lbl">{{ $invoice->tax_label ?? 'IVA' }}:</td>
                <td class="val">
                    @if ($invoice->tax_mode === 'EXEMPT' || (float) ($invoice->tax_rate ?? 0) === 0.0)
                        {{ $labels['exempt'] }}
                    @else
                        {{ $fmt($invoice->tax_amount) }}
                        ({{ rtrim(rtrim(number_format((float) $invoice->tax_rate, 2), '0'), '.') }}%)
                    @endif
                </td>
            </tr>
            <tr class="grand">
                <td class="lbl">
                    {{ $invoice->is_paid ? $labels['total_paid'] : $labels['total_due'] }}:
                </td>
                <td class="val">{{ $fmt($invoice->total) }}</td>
            </tr>
        </table>
    </div>

    @if (! empty($pdf['notes_body']))
        <div class="notice-box">
            <h3>{{ $labels['fiscal_heading'] }}</h3>
            <p>{{ $pdf['notes_body'] }}</p>
        </div>
    @endif

    @if (! empty($pdf['additional_notes']))
        <div class="notice-box">
            <h3>{{ $labels['additional_notes_heading'] }}</h3>
            <p>{{ $pdf['additional_notes'] }}</p>
        </div>
    @endif

    @if ($invoice->is_paid)
        @php
            $received = $invoice->amount_received ?? $invoice->total;
        @endphp
        <div class="pago-recibido">
            <h3>{{ $labels['payment_received'] }}</h3>
            @if ($invoice->payment_method)
                <p><strong>{{ $labels['payment_method'] }}:</strong> {{ $invoice->payment_method }}</p>
            @endif
            @if ($invoice->transfer_number)
                <p><strong>{{ $labels['transfer_number'] }}:</strong> {{ $invoice->transfer_number }}</p>
            @endif
            @if ($pdf['payment_date'] !== '')
                <p><strong>{{ $labels['payment_date'] }}:</strong> {{ $pdf['payment_date'] }}</p>
            @endif
            <p><strong>{{ $labels['amount_received'] }}:</strong> {{ $fmt($received) }}</p>
        </div>
    @elseif (! empty($company['bank_iban']) || ! empty($company['bank_beneficiary']))
        <div class="bank">
            <h3>{{ $labels['bank_heading'] }}</h3>
            @if (! empty($company['bank_beneficiary']))
                <p><strong>{{ $labels['beneficiary'] }}:</strong> {{ $company['bank_beneficiary'] }}</p>
            @endif
            @if (! empty($company['bank_iban']))
                <p><strong>IBAN:</strong> {{ $company['bank_iban'] }}</p>
            @endif
            @if (! empty($company['bank_bic']))
                <p><strong>BIC/SWIFT:</strong> {{ $company['bank_bic'] }}</p>
            @endif
            @if (! empty($company['bank_name']))
                <p><strong>{{ $labels['bank'] }}:</strong> {{ $company['bank_name'] }}</p>
            @endif
        </div>
    @endif

    <div class="footer">{{ $labels['footer_thanks'] }}</div>
</body>
</html>
