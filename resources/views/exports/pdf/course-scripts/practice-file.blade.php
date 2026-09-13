@extends('exports.pdf.course-scripts.layout')

@php
    /** @var array<string, mixed> $artifact */
@endphp

@section('doc_title', $artifact['file_name'])

@section('content')
    @include('exports.pdf.course-scripts.partials.content-blocks', ['blocks' => (array) $artifact['content_blocks']])
@endsection
