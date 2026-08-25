@extends('exports.pdf.layout')

@section('report_heading', 'Students')
@section('report_subtitle', 'LMS learner profiles shared across the academy.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>DNI</th>
                <th>Lifecycle</th>
                <th>Active</th>
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
                    <td>{{ $row['DNI'] }}</td>
                    <td>{{ $row['Lifecycle'] }}</td>
                    <td>{{ $row['Active'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty-state">No students to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
