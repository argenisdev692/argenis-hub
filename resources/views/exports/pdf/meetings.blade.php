@extends('exports.pdf.layout')

@section('report_heading', 'Meetings')
@section('report_subtitle', 'Internal scheduling — meetings and their attendees.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Organizer</th>
                <th>Attendees</th>
                <th>Status</th>
                <th>Meeting Status</th>
                <th>Starts At</th>
                <th>Ends At</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Title'] }}</td>
                    <td>{{ $row['Organizer'] }}</td>
                    <td>{{ $row['Attendees'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                    <td>{{ $row['Meeting Status'] }}</td>
                    <td>{{ $row['Starts At'] }}</td>
                    <td>{{ $row['Ends At'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty-state">No meetings to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
