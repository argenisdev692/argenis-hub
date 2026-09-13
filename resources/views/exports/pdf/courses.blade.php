@extends('exports.pdf.layout')

@section('report_heading', 'Courses')
@section('report_subtitle', 'Course scripts filtered by the current list criteria.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Title</th>
                <th>Language</th>
                <th>Generation Status</th>
                <th class="num">Videos</th>
                <th class="num">Generated</th>
                <th>Status</th>
                <th>Created</th>
                <th>Updated</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Reference'] }}</td>
                    <td>{{ $row['Title'] }}</td>
                    <td>{{ $row['Language'] }}</td>
                    <td>{{ $row['Generation Status'] }}</td>
                    <td class="num">{{ $row['Videos'] }}</td>
                    <td class="num">{{ $row['Generated'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Updated'] }}</td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="empty-state">No courses to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
