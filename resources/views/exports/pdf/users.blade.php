@extends('exports.pdf.layout')

@section('report_heading', 'Users')
@section('report_subtitle', 'Directory of registered platform users.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Username</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Name'] }}</td>
                    <td>{{ $row['Email'] }}</td>
                    <td>{{ $row['Username'] }}</td>
                    <td>{{ $row['Phone'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                    <td>{{ $row['Created At'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state">No users to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
