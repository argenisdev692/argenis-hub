@extends('exports.pdf.layout')

@section('report_heading', 'Blog Categories')
@section('report_subtitle', 'Content taxonomy used across the blog.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Description</th>
                <th>Author</th>
                <th>Created</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Name'] }}</td>
                    <td>{{ $row['Description'] }}</td>
                    <td>{{ $row['Author'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state">No categories to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
