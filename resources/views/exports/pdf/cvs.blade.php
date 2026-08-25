@extends('exports.pdf.layout')

@section('report_heading', 'CVs')
@section('report_subtitle', 'Uploaded resumes (PDF / Markdown) by niche.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Niche</th>
                <th>Primary</th>
                <th>Type</th>
                <th>Filename</th>
                <th>Owner</th>
                <th>Created</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Title'] }}</td>
                    <td>{{ $row['Niche'] }}</td>
                    <td>{{ $row['Primary'] }}</td>
                    <td>{{ $row['Type'] }}</td>
                    <td>{{ $row['Filename'] }}</td>
                    <td>{{ $row['Owner'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty-state">No CVs to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
