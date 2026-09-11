@extends('exports.pdf.layout')

@section('report_heading', 'Invoices')
{{-- Names the criteria the export was run with, so a printed report is not
     ambiguous about whether it holds every invoice or only the unpaid ones. --}}
@section('report_subtitle', $filterSummary ?? 'Issued invoices filtered by the current list criteria.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Number</th>
                <th>Client</th>
                <th>Issue date</th>
                <th>Due date</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Number'] }}</td>
                    <td>{{ $row['Client'] }}</td>
                    <td>{{ $row['Issue date'] }}</td>
                    <td>{{ $row['Due date'] }}</td>
                    <td>{{ $row['Total'] }}</td>
                    <td>{{ $row['Paid'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state">No invoices to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
