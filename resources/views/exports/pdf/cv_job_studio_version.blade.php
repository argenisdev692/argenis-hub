<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>CV export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; }
        h1 { font-size: 14pt; text-align: center; margin-bottom: 0; }
        .contact { text-align: center; font-size: 9pt; margin-top: 2px; margin-bottom: 8px; }
        h2 { font-size: 11pt; margin-bottom: 2px; border-bottom: 1px solid #333; }
        li { margin-bottom: 2px; }
        .skills { margin: 2px 0 6px 0; }
        .meta { color: #555; font-size: 8pt; }
    </style>
</head>
<body>
@if (! empty($contact['name'] ?? null))
    <h1>{{ $contact['name'] }}</h1>
    @if (! empty($contact['lines'] ?? []))
        <p class="contact">{{ implode(' · ', $contact['lines']) }}</p>
    @endif
@endif
@foreach (($version['sections'] ?? []) as $section)
    <h2>{{ $section['heading'] ?? '' }}</h2>
    @if (! empty($section['skills_line'] ?? null))
        <p class="skills">{{ $section['skills_line'] }}</p>
    @endif
    @if (! empty($section['bullets'] ?? []))
        <ul>
            @foreach ($section['bullets'] as $bullet)
                <li>{{ is_array($bullet) ? ($bullet['text'] ?? '') : $bullet }}</li>
            @endforeach
        </ul>
    @endif
@endforeach
<p class="meta">Generated {{ $generatedAt }} — heuristic content, not a vendor ATS score.</p>
</body>
</html>
