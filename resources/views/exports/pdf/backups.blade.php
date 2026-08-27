@extends('exports.pdf.layout')

@section('report_heading', 'Database Backups')
@section('report_subtitle', 'Archive index for the scheduled and on-demand database backups.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Filename</th>
                <th>Disk</th>
                <th class="num">Size</th>
                <th>Status</th>
                <th>Connection</th>
                <th>Started</th>
                <th>Finished</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Filename'] }}</td>
                    <td>{{ $row['Disk'] }}</td>
                    <td class="num">{{ $row['Size'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                    <td>{{ $row['Connection'] }}</td>
                    <td>{{ $row['Started'] }}</td>
                    <td>{{ $row['Finished'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty-state">No backups to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
