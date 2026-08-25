@extends('exports.pdf.layout')

@section('report_heading', 'Availability Rules')
@section('report_subtitle', 'Recurring weekly availability schedule.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th class="num">Day</th>
                <th class="num">Start</th>
                <th class="num">End</th>
                <th class="num">Availability</th>
                <th class="num">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="num">{{ $row['Day'] }}</td>
                    <td class="num">{{ $row['Start'] }}</td>
                    <td class="num">{{ $row['End'] }}</td>
                    <td class="num">{{ $row['Availability'] }}</td>
                    <td class="num">{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state">No availability rules to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
