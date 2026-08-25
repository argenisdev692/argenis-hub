@extends('exports.pdf.layout')

@section('report_heading', 'Posts')
@section('report_subtitle', 'Blog content, authorship and SEO scoring.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Category</th>
                <th>Author</th>
                <th>Publication Status</th>
                <th>SEO Score</th>
                <th>Created</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Title'] }}</td>
                    <td>{{ $row['Category'] }}</td>
                    <td>{{ $row['Author'] }}</td>
                    <td>{{ $row['Publication Status'] }}</td>
                    <td>{{ $row['SEO Score'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state">No posts to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
