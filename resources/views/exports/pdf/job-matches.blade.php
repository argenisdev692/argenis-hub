@extends('exports.pdf.layout')

@section('report_heading', 'Job Matches')
@section('report_subtitle', 'AI-scored job postings discovered by Resume Studio.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Job Title</th>
                <th>Company</th>
                <th>Score</th>
                <th>Application</th>
                <th>Status</th>
                <th>Source</th>
                <th>Owner</th>
                <th>First Seen</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Job Title'] }}</td>
                    <td>{{ $row['Company'] }}</td>
                    <td>{{ $row['Score'] }}</td>
                    <td>{{ $row['Application'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                    <td>{{ $row['Source'] }}</td>
                    <td>{{ $row['Owner'] }}</td>
                    <td>{{ $row['First Seen'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty-state">No job matches to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
