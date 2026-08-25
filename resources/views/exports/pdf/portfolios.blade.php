@extends('exports.pdf.layout')

@section('report_heading', 'Portfolios')
@section('report_subtitle', 'Project showcase catalog for the public landing page and CRM.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Client</th>
                <th>Type</th>
                <th>Tech Stack</th>
                <th>Public</th>
                <th>Published</th>
                <th>Owner</th>
                <th>Created</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Title'] }}</td>
                    <td>{{ $row['Client'] }}</td>
                    <td>{{ $row['Type'] }}</td>
                    <td>{{ $row['Tech Stack'] }}</td>
                    <td>{{ $row['Public'] }}</td>
                    <td>{{ $row['Published'] }}</td>
                    <td>{{ $row['Owner'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="empty-state">No portfolios to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
