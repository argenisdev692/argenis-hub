@extends('exports.pdf.layout')

@section('report_heading', 'Roles')
@section('report_subtitle', 'Access roles and their assigned permissions.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Guard</th>
                <th>Permissions</th>
                <th>Created</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Name'] }}</td>
                    <td>{{ $row['Guard'] }}</td>
                    <td>{{ $row['Permissions'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state">No roles to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
