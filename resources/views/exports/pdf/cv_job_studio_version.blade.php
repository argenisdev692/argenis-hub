<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>CV export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; }
        h1 { font-size: 14pt; }
        h2 { font-size: 11pt; margin-bottom: 2px; }
        li { margin-bottom: 2px; }
        .meta { color: #555; font-size: 8pt; }
    </style>
</head>
<body>
@foreach (($version['sections'] ?? []) as $section)
    <h2>{{ $section['heading'] ?? '' }}</h2>
    <ul>
        @foreach (($section['bullets'] ?? []) as $bullet)
            <li>{{ is_array($bullet) ? ($bullet['text'] ?? '') : $bullet }}</li>
        @endforeach
    </ul>
@endforeach
<p class="meta">Generated {{ $generatedAt }} — heuristic content, not a vendor ATS score.</p>
</body>
</html>
