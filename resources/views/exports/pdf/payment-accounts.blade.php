@extends('exports.pdf.layout')

@section('report_heading', 'Payment Accounts')
@section('report_subtitle', 'Settlement rails invoices snapshot at issue time. Account identifiers are masked.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Label</th>
                <th>Method</th>
                <th>Currency</th>
                <th>Beneficiary</th>
                <th>Bank</th>
                <th>Account</th>
                <th>Default</th>
                <th>Active</th>
                <th>Created</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Label'] }}</td>
                    <td>{{ $row['Method'] }}</td>
                    <td>{{ $row['Currency'] }}</td>
                    <td>{{ $row['Beneficiary'] }}</td>
                    <td>{{ $row['Bank'] }}</td>
                    <td>{{ $row['Account'] }}</td>
                    <td>{{ $row['Default'] }}</td>
                    <td>{{ $row['Active'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="10"><div class="empty-state">No payment accounts to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
