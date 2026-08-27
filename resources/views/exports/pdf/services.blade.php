@extends('exports.pdf.layout')

@section('report_heading', 'Services')
@section('report_subtitle', 'Catalog entries shown in the public booking form.')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Description</th>
                <th>Status</th>
                <th class="num">Order</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Name'] }}</td>
                    <td>{{ $row['Slug'] }}</td>
                    <td>{{ $row['Description'] }}</td>
                    <td>{{ $row['Status'] }}</td>
                    <td class="num">{{ $row['Order'] }}</td>
                    <td>{{ $row['Created'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state">No services to display.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
