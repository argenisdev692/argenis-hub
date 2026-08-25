@extends('exports.pdf.layout')

@section('report_heading', 'Social Media Content')
@section('report_subtitle', 'AI-generated multi-platform content, funnel stage and quality scoring.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Topic</th>
                <th>Funnel Stage</th>
                <th>Content Status</th>
                <th>Overall Score</th>
                <th>Scores Passed</th>
                <th>Created</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Topic'] }}</td>
                    <td>{{ $row['Funnel Stage'] }}</td>
                    <td>{{ $row['Content Status'] }}</td>
                    <td>{{ $row['Overall Score'] }}</td>
                    <td>{{ $row['Scores Passed'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state">No social media content to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
