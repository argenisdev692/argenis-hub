@extends('exports.pdf.course-scripts.layout')

@php
    /** @var \Modules\CourseScripts\Domain\ValueObjects\ScriptDocument $document */
    $header = $document->technicalHeader;
    $notes = $document->recordingNotes;
@endphp

@section('doc_title', $t['script'].' '.$document->videoNumber.' — '.$document->videoTitle)

@section('content')
    <h1>{{ $t['script'] }} {{ $document->videoNumber }} — {{ $document->videoTitle }}</h1>

    <h2>{{ $t['technical'] }}</h2>
    <table class="meta">
        <tr><td class="label">{{ $t['duration'] }}</td><td>{{ $header['duration_minutes'] }} {{ $t['minutes'] }}</td></tr>
        @if (($header['block_number'] ?? null) !== null)
            <tr><td class="label">{{ $t['block'] }}</td><td>{{ $header['block_number'] }} – {{ $header['block_title'] }}</td></tr>
        @endif
        <tr><td class="label">{{ $t['format'] }}</td><td>{{ $header['recording_format'] ?? '' }}</td></tr>
        <tr><td class="label">{{ $t['video'] }}</td><td>{{ $header['position_label'] ?? '' }}</td></tr>
    </table>

    @unless ($document->isGrounded)
        <div class="notice">{{ $t['ungrounded'] }}</div>
    @endunless
    @if ($document->passedReview === false)
        <div class="notice">{{ $t['not_passed'] }}</div>
    @endif

    <h2>{{ $t['objectives'] }}</h2>
    <ul>
        @foreach ($document->learningObjectives as $objective)
            <li>{{ $objective }}</li>
        @endforeach
    </ul>
    <div class="continuity"><strong>{{ $t['continuity'] }}:</strong> {{ $document->continuityNote }}</div>

    @foreach ($document->sections as $section)
        @php($isSub = ($section['parent_number'] ?? null) !== null)
        @if ($isSub)
            <h3>
        @else
            <h2>
        @endif
            {{ $section['number'] }}. {{ $section['title'] }}
            @if (! $isSub || (int) $section['minutes'] > 0)
                ({{ $section['minutes'] }} {{ (int) $section['minutes'] === 1 ? $t['minute'] : $t['minutes'] }})
            @endif
            @if (($section['demo_label'] ?? null) !== null)
                <span class="demo">{{ $section['demo_label'] }}</span>
            @endif
        @if ($isSub)
            </h3>
        @else
            </h2>
        @endif

        @foreach ((array) ($section['segments'] ?? []) as $segment)
            @switch($segment['type'])
                @case('narration')
                    <div class="narration">{{ $segment['text'] }}</div>
                    @break
                @case('on_screen_prompt')
                    <div class="prompt"><span class="tag">{{ $t['prompt'] }}</span><pre>{{ $segment['prompt'] }}</pre></div>
                    @break
                @case('expected_result')
                    <div class="result"><span class="tag">{{ $t['expected_result'] }}</span><p>{{ $segment['text'] }}</p></div>
                    @break
                @case('on_screen_actions')
                    <div class="actions"><span class="tag">{{ $t['actions'] }}</span>
                        <ul>@foreach ((array) $segment['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>
                    </div>
                    @break
                @case('show_on_screen')
                    <div class="show">
                        <span class="tag">{{ $t['show'] }}@if ($segment['read_aloud'] ?? false) ({{ $t['read_aloud'] }})@endif @if (($segment['practice_file'] ?? null) !== null) · {{ $t['practice_file'] }}: {{ $segment['practice_file'] }}@endif</span>
                        <p>{{ $segment['text'] }}</p>
                    </div>
                    @break
                @case('on_screen_table')
                    <span class="table-tag">{{ $t['table'] }}</span>
                    @include('exports.pdf.course-scripts.partials.table', ['header' => (array) $segment['table_columns'], 'rows' => (array) $segment['table_rows'], 'totalRow' => false])
                    @break
                @case('presenter_note')
                    <div class="note"><span class="tag">{{ $t['note'] }}:</span>{{ implode(' · ', array_filter([...(array) ($segment['items'] ?? []), $segment['text'] ?? ''])) }}</div>
                    @break
            @endswitch
        @endforeach
    @endforeach

    <h2>{{ $t['summary'] }}</h2>
    <ul>
        @foreach ($document->summaryPoints as $point)
            <li>{{ $point }}</li>
        @endforeach
    </ul>

    @if ($document->nextVideo !== null)
        <p><strong>{{ $t['next_video'] }}:</strong> {{ $document->nextVideo['title'] }} ({{ $t['video'] }} {{ $document->nextVideo['number'] }})</p>
        @if (trim((string) ($document->nextVideo['handoff'] ?? '')) !== '')
            <p>{{ $document->nextVideo['handoff'] }}</p>
        @endif
    @endif

    <h2>{{ $t['recording_notes'] }}</h2>
    @foreach (['preparation' => $t['preparation'], 'during_recording' => $t['during_recording']] as $key => $label)
        @if ((array) ($notes[$key] ?? []) !== [])
            <h3>{{ $label }}</h3>
            <ul>@foreach ((array) $notes[$key] as $item)<li>{{ $item }}</li>@endforeach</ul>
        @endif
    @endforeach
    <h3>{{ $t['tools'] }}</h3>
    @if ((array) ($notes['tools_required'] ?? []) === [])
        <p>{{ $notes['tools_none_reason'] ?? '' }}</p>
    @else
        <ul>@foreach ((array) $notes['tools_required'] as $tool)<li>{{ $tool }}</li>@endforeach</ul>
    @endif
    @if (trim((string) ($notes['continuity'] ?? '')) !== '')
        <h3>{{ $t['continuity_notes'] }}</h3>
        <p>{{ $notes['continuity'] }}</p>
    @endif
    @if ((array) ($notes['organisations_used'] ?? []) !== [])
        <h3>{{ $t['organisations'] }}</h3>
        <p>{{ implode(', ', (array) $notes['organisations_used']) }}</p>
    @endif

    <h2>{{ $t['checklist'] }}</h2>
    <ul class="checklist">
        @foreach ($document->verificationChecklist as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>

    <p class="endmark">{{ $t['end'] }} {{ $document->videoNumber }} · {{ $t['version'] }} {{ $document->version }}.0 · {{ $document->generatedOn }}</p>
@endsection
