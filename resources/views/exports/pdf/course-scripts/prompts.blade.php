@extends('exports.pdf.course-scripts.layout')

@php
    /** @var \Modules\CourseScripts\Domain\ValueObjects\ScriptDocument $document */
@endphp

@section('doc_title', $t['prompts_title'].' '.$document->videoNumber.' — '.$document->videoTitle)

@section('content')
    <h1>{{ $t['prompts_title'] }} {{ $document->videoNumber }} — {{ $document->videoTitle }}</h1>
    <p>{{ $t['prompts_intro'] }}</p>

    @foreach ($document->prompts() as $index => $prompt)
        <h2>
            {{ $index + 1 }}. {{ $t['section'] }} {{ $prompt['section_number'] }} — {{ $prompt['section_title'] }}
            @if ($prompt['demo_label'] !== null)
                <span class="demo">{{ $prompt['demo_label'] }}</span>
            @endif
        </h2>
        @if ($prompt['practice_files'] !== [])
            <p><strong>{{ $t['paste_first'] }}:</strong> {{ implode(', ', $prompt['practice_files']) }}</p>
        @endif
        <div class="prompt"><span class="tag">{{ $t['prompt'] }}</span><pre>{{ $prompt['prompt'] }}</pre></div>
    @endforeach
@endsection
