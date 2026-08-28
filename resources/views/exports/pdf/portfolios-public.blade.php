@extends('exports.pdf.layout')

@section('report_heading', 'Portfolio Showcase')
@section('report_subtitle', 'Published project showcase for the public landing page.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Client</th>
                <th>Type</th>
                <th>Tech Stack</th>
                <th>Live URL</th>
                <th>Published</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Title'] }}</td>
                    <td>{{ $row['Client'] }}</td>
                    <td>{{ $row['Type'] }}</td>
                    <td>{{ $row['Tech Stack'] }}</td>
                    <td>{{ $row['Live URL'] }}</td>
                    <td>{{ $row['Published'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state">No published portfolios to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
