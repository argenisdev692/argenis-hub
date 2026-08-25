@extends('exports.pdf.layout')

@section('report_heading', 'Attendance sheet')
@section('report_subtitle', $title ?? 'Classroom attendance')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($headers as $header)
                        <td>{{ $row[$header] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headers) }}"><div class="empty-state">No attendance rows.</div></td></tr>
            @endforelse
        </tbody>
    </table>
    <p style="margin-top: 12px; font-size: 10px; color: #666;">
        Codes: P=Present, A=Absent, L=Late, J=Justified · Generated {{ $generatedAt ?? '' }}
    </p>
@endsection
