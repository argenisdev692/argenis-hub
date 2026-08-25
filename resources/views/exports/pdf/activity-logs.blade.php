@extends('exports.pdf.layout')

@section('report_heading', 'Activity Log')
@section('report_subtitle', 'Audit trail of actions recorded across the system.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>When</th>
                <th>Actor</th>
                <th>Event</th>
                <th>Description</th>
                <th>Subject</th>
                <th>Log</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['When'] }}</td>
                    <td>{{ $row['Actor'] }}</td>
                    <td>{{ $row['Event'] }}</td>
                    <td>{{ $row['Description'] }}</td>
                    <td>{{ $row['Subject'] }}</td>
                    <td>{{ $row['Log'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state">No activity to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
