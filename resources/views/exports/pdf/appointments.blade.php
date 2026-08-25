@extends('exports.pdf.layout')

@section('report_heading', 'Appointments')
@section('report_subtitle', 'Sales-lead pipeline and scheduled appointments.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Company</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Lead Status</th>
                <th>Meeting Status</th>
                <th>Scheduled At</th>
                <th>Created</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Name'] }}</td>
                    <td>{{ $row['Company'] }}</td>
                    <td>{{ $row['Email'] }}</td>
                    <td>{{ $row['Phone'] }}</td>
                    <td>{{ $row['Lead Status'] }}</td>
                    <td>{{ $row['Meeting Status'] }}</td>
                    <td>{{ $row['Scheduled At'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="empty-state">No appointments to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
