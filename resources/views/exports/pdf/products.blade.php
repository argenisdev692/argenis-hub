@extends('exports.pdf.layout')

@section('report_heading', 'Products')
@section('report_subtitle', 'Billable classroom and video catalog items.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Client</th>
                <th>Price</th>
                <th>Lifecycle</th>
                <th>Level</th>
                <th>Language</th>
                <th>Owner</th>
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
                    <td>{{ $row['Lifecycle'] }}</td>
                    <td>{{ $row['Level'] }}</td>
                    <td>{{ $row['Language'] }}</td>
                    <td>{{ $row['Owner'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="10"><div class="empty-state">No products to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
