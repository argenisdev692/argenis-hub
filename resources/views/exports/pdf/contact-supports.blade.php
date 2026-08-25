@extends('exports.pdf.layout')

@section('report_heading', 'Contact Support')
@section('report_subtitle', 'Inbound support requests submitted by users.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Subject</th>
                <th class="num">Read</th>
                <th class="num">SMS Consent</th>
                <th>Created</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Name'] }}</td>
                    <td>{{ $row['Email'] }}</td>
                    <td>{{ $row['Phone'] }}</td>
                    <td>{{ $row['Subject'] }}</td>
                    <td class="num">{{ $row['Read'] }}</td>
                    <td class="num">{{ $row['SMS Consent'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty-state">No support requests to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
