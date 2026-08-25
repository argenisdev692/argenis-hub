@extends('exports.pdf.layout')

@section('report_heading', 'Availability Exceptions')
@section('report_subtitle', 'Date-specific overrides to the weekly schedule.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th class="num">Date</th>
                <th class="num">Availability</th>
                <th class="num">Start</th>
                <th class="num">End</th>
                <th>Reason</th>
                <th class="num">Source</th>
                <th class="num">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="num">{{ $row['Date'] }}</td>
                    <td class="num">{{ $row['Availability'] }}</td>
                    <td class="num">{{ $row['Start'] }}</td>
                    <td class="num">{{ $row['End'] }}</td>
                    <td>{{ $row['Reason'] }}</td>
                    <td class="num">{{ $row['Source'] }}</td>
                    <td class="num">{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state">No exceptions to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
