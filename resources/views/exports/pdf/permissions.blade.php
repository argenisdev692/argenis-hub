@extends('exports.pdf.layout')

@section('report_heading', 'Permissions')
@section('report_subtitle', 'Granular permissions and the roles that grant them.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Guard</th>
                <th>Roles</th>
                <th>Created</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Name'] }}</td>
                    <td>{{ $row['Guard'] }}</td>
                    <td>{{ $row['Roles'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state">No permissions to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
