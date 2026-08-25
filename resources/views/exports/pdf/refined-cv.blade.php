{{--
    ATS-friendly resume PDF — human document look (no corporate brand band).
    Portrait A4, single column, system-safe DejaVu Sans, standard headings.
--}}
<!DOCTYPE html>
<html lang="{{ $htmlLang ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $docTitle ?? 'Resume' }}</title>
    <style>
        @page {
            margin: 48px 52px 48px 52px;
        }

        * {
            font-family: DejaVu Sans, sans-serif;
        }

        html, body {
            margin: 0;
            padding: 0;
            color: #111827;
            font-size: 10.5pt;
            line-height: 1.45;
        }

        h1 {
            margin: 0 0 6px 0;
            font-size: 18pt;
            font-weight: bold;
            letter-spacing: 0.01em;
            color: #0f172a;
        }

        h2 {
            margin: 16px 0 6px 0;
            padding-bottom: 3px;
            font-size: 11.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid #cbd5e1;
            color: #0f172a;
        }

        h3 {
            margin: 10px 0 2px 0;
            font-size: 10.5pt;
            font-weight: bold;
            color: #1e293b;
        }

        p {
            margin: 0 0 8px 0;
        }

        ul, ol {
            margin: 0 0 8px 0;
            padding-left: 16px;
        }

        li {
            margin: 0 0 3px 0;
        }

        a {
            color: #1e293b;
            text-decoration: none;
        }

        strong {
            font-weight: bold;
        }

        .meta {
            margin: 0 0 14px 0;
            font-size: 9pt;
            color: #475569;
        }

        .target {
            margin: 0 0 12px 0;
            font-size: 9pt;
            color: #64748b;
        }

        /* DomPDF: avoid page-break inside list items when possible */
        li, p, h2, h3 {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    @if (!empty($heading))
        <h1>{{ $heading }}</h1>
    @endif

    @if (!empty($contactLine))
        <p class="meta">{{ $contactLine }}</p>
    @endif

    @if (!empty($targetJobTitle))
        <p class="target">Target role: {{ $targetJobTitle }}</p>
    @endif

    {!! $bodyHtml !!}
</body>
</html>
