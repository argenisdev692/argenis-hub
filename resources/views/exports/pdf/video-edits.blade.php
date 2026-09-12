@extends('exports.pdf.layout')

@section('report_heading', 'Video edits')
{{-- Names the criteria the export was run with, so a printed report is not
     ambiguous about whether it holds every edit or only March's failures. --}}
@section('report_subtitle', $filterSummary ?? 'Edit history filtered by the current list criteria.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Mode</th>
                <th>Status</th>
                <th class="num">Clips</th>
                <th class="num">Original</th>
                <th class="num">Final</th>
                <th class="num">Removed</th>
                <th class="num">Cuts</th>
                <th>Created</th>
                <th>Completed</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Reference'] }}</td>
                    <td>{{ $row['Mode'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                    <td class="num">{{ $row['Clips'] }}</td>
                    <td class="num">{{ $row['Original'] }}</td>
                    <td class="num">{{ $row['Final'] }}</td>
                    <td class="num">{{ $row['Removed'] }}</td>
                    <td class="num">{{ $row['Cuts'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Completed'] }}</td>
                </tr>
            @empty
                <tr><td colspan="10"><div class="empty-state">No video edits to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
