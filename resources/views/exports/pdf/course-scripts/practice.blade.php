@extends('exports.pdf.course-scripts.layout')

@php
    /** @var \Modules\CourseScripts\Domain\ValueObjects\PracticeDocument $document */
    $files = array_map(static fn (array $artifact): string => (string) $artifact['file_name'], $document->artifacts);
@endphp

@section('doc_title', $document->headerTitle)

@section('content')
    <h1>{{ $document->headerTitle }}</h1>

    <p><strong>{{ $t['files'] }}:</strong> {{ implode(', ', array_map(static fn (string $file): string => $file.'.pdf', $files)) }}</p>
    <p>{{ $document->setupInstruction }}</p>
    <ul>
        @foreach ($document->usage as $usage)
            <li><strong>{{ $t['use_in'] }}:</strong> {{ $usage['demo_label'] }} ({{ $t['section'] }} {{ $usage['section_number'] }}) — {{ $usage['purpose'] }}@isset($usage['file_name']) · {{ $usage['file_name'] }}@endisset</li>
        @endforeach
    </ul>

    <div class="notice"><strong>{{ $t['instructor_note'] }}:</strong> {{ $document->instructorNote }}</div>

    @if ($document->designedContrasts !== [])
        <h3>{{ $t['contrasts'] }}</h3>
        @include('exports.pdf.course-scripts.partials.table', [
            'header' => [$t['dimension'], ...$files, $t['intended_effect']],
            'rows' => array_map(static fn (array $contrast): array => [
                (string) $contrast['dimension'],
                ...array_map(static fn (string $file): string => (string) (array_find((array) $contrast['values'], static fn (array $value): bool => $value['file_name'] === $file)['value'] ?? '—'), $files),
                (string) $contrast['intended_effect'],
            ], $document->designedContrasts),
            'totalRow' => false,
        ])
    @endif

    <p class="note">{{ $t['fictional'] }}</p>

    @foreach ($document->artifacts as $artifact)
        <div class="page-break"></div>
        @include('exports.pdf.course-scripts.partials.content-blocks', ['blocks' => (array) $artifact['content_blocks']])
    @endforeach
@endsection
