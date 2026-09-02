@extends('exports.pdf.layout')

@section('report_heading', 'Products')
@section('report_subtitle', 'Billable catalog — training courses and video courses.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Client</th>
                <th>Price</th>
                <th>Unit</th>
                <th>Hours</th>
                <th>Sessions</th>
                <th>Modality</th>
                <th>Catalog</th>
                <th>Created</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Title'] }}</td>
                    <td>{{ $row['Type'] }}</td>
                    <td>{{ $row['Client'] }}</td>
                    <td>{{ $row['Price'] }}</td>
                    <td>{{ $row['Unit'] }}</td>
                    <td>{{ $row['Hours'] }}</td>
                    <td>{{ $row['Sessions'] }}</td>
                    <td>{{ $row['Modality'] }}</td>
                    <td>{{ $row['Catalog'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="11"><div class="empty-state">No products to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
