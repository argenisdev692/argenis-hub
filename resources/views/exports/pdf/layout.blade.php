{{--
    Corporate PDF export base layout.

    Renders a branded header band (logo + company name from company_data / APP_URL),
    a title block, the child report table, and a fixed footer with contact details
    and live page numbers. Consumed via `@extends('exports.pdf.layout')`.

    Contract for child views:
      @section('report_heading')   required — the report name (e.g. "Users")
      @section('report_subtitle')  optional — one-line description under the heading
      @section('content')          required — the report body (usually <table class="data-table">)

    Shared data:
      $company      injected by SharedServiceProvider view composer (CompanyProfile::pdfBranding())
      $generatedAt  passed by each export controller
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('doc_title', $company['name'].' — '.trim($__env->yieldContent('report_heading', 'Export')))</title>
    <style>
        @page {
            margin: 116px 40px 74px 40px;
        }

        * {
            font-family: DejaVu Sans, sans-serif;
        }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            font-size: 10.5px;
            line-height: 1.45;
            color: #0b1020;
        }

        /* ---------- Fixed brand header ---------- */
        .pdf-header {
            position: fixed;
            top: -96px;
            left: 0;
            right: 0;
            height: 66px;
            background-color: #7c3aed;
            background-image: linear-gradient(100deg, #7c3aed 0%, #4f9fe0 60%, #06b6d4 100%);
            border-bottom: 3px solid #06b6d4;
        }

        .pdf-header table {
            width: 100%;
            height: 66px;
            border-collapse: collapse;
        }

        .pdf-header td {
            vertical-align: middle;
            padding: 0 22px;
        }

        .pdf-header .brand-logo {
            height: 30px;
            width: auto;
        }

        .pdf-header .brand-name {
            display: block;
            color: #ffffff;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: .01em;
        }

        .pdf-header .brand-contact {
            display: block;
            color: rgba(255, 255, 255, 0.82);
            font-size: 8.5px;
            margin-top: 2px;
        }

        /* ---------- Title block (page-flow) ---------- */
        .doc-title {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0 10px 0;
        }

        .doc-title .eyebrow {
            color: #7c3aed;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .doc-title h1 {
            margin: 3px 0 2px 0;
            font-size: 21px;
            font-weight: bold;
            color: #0b1020;
        }

        .doc-title .subtitle {
            color: #6b7280;
            font-size: 10px;
        }

        .meta-card {
            text-align: right;
            vertical-align: bottom;
        }

        .meta-card .label {
            color: #9ca3af;
            font-size: 7.5px;
            font-weight: bold;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .meta-card .value {
            color: #7c3aed;
            font-size: 10px;
            font-weight: bold;
        }

        .title-rule {
            height: 2px;
            background-color: #7c3aed;
            background-image: linear-gradient(90deg, #7c3aed, #06b6d4 70%, rgba(6, 182, 212, 0));
            margin: 0 0 14px 0;
            font-size: 0;
            line-height: 0;
        }

        /* ---------- Data table ---------- */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.data-table thead th {
            background-color: #f5f0ff;
            color: #5b21b6;
            text-align: left;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: .06em;
            text-transform: uppercase;
            padding: 7px 9px;
            border-bottom: 1.5px solid #7c3aed;
        }

        table.data-table tbody td {
            padding: 6px 9px;
            font-size: 10px;
            color: #0b1020;
            border-bottom: 1px solid #eef0f4;
            vertical-align: top;
        }

        table.data-table tbody tr:nth-child(even) td {
            background-color: #f8f8fc;
        }

        /* Numeric / centred columns opt-in via <th class="num"> / <td class="num"> */
        table.data-table .num {
            text-align: center;
        }

        .empty-state {
            padding: 26px;
            text-align: center;
            color: #9ca3af;
            font-size: 11px;
            border: 1px dashed #e5e7eb;
            border-radius: 6px;
        }

        /* ---------- Fixed footer ---------- */
        .pdf-footer {
            position: fixed;
            bottom: -52px;
            left: 0;
            right: 0;
            height: 40px;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
        }

        .pdf-footer table {
            width: 100%;
            border-collapse: collapse;
        }

        .pdf-footer td {
            font-size: 8px;
            color: #9ca3af;
            vertical-align: middle;
        }

        .pdf-footer .foot-brand {
            color: #6b7280;
            font-weight: bold;
        }

        .pdf-footer .foot-right {
            text-align: right;
        }

        .pdf-footer .pageno:after {
            content: counter(page) " / " counter(pages);
        }
    </style>
</head>
<body>
    <header class="pdf-header">
        <table>
            <tr>
                <td>
                    @if (! empty($company['logo_data_uri']))
                        <img class="brand-logo" src="{{ $company['logo_data_uri'] }}" alt="">
                    @else
                        <span class="brand-name">{{ $company['name'] }}</span>
                    @endif
                </td>
                <td style="text-align: right;">
                    <span class="brand-name">{{ $company['name'] }}</span>
                    @if (! empty($company['website']))
                        <span class="brand-contact">{{ preg_replace('#^https?://#', '', (string) $company['website']) }}</span>
                    @endif
                </td>
            </tr>
        </table>
    </header>

    <footer class="pdf-footer">
        <table>
            <tr>
                <td>
                    <span class="foot-brand">{{ $company['name'] }}</span>@if (! empty($company['email'])) &nbsp;·&nbsp; {{ $company['email'] }}@endif @if (! empty($company['phone'])) &nbsp;·&nbsp; {{ $company['phone'] }}@endif
                </td>
                <td class="foot-right">
                    Page <span class="pageno"></span>
                </td>
            </tr>
        </table>
    </footer>

    <main>
        <table class="doc-title">
            <tr>
                <td>
                    <span class="eyebrow">Report</span>
                    <h1>@yield('report_heading', 'Export')</h1>
                    @hasSection('report_subtitle')
                        <div class="subtitle">@yield('report_subtitle')</div>
                    @endif
                </td>
                <td class="meta-card">
                    <div class="label">Generated</div>
                    <div class="value">{{ $generatedAt ?? now()->format('F j, Y H:i') }}</div>
                </td>
            </tr>
        </table>
        <div class="title-rule"></div>

        @yield('content')
    </main>
</body>
</html>
