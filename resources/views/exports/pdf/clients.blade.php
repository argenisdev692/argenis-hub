@extends('exports.pdf.layout')

@section('report_heading', 'Clients')
@section('report_subtitle', 'CRM contacts owned by instructors / creators.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Lifecycle</th>
                <th>Owner</th>
                <th>Created</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Name'] }}</td>
                    <td>{{ $row['Email'] }}</td>
                    <td>{{ $row['Phone'] }}</td>
                    <td>{{ $row['Lifecycle'] }}</td>
                    <td>{{ $row['Owner'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state">No clients to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
