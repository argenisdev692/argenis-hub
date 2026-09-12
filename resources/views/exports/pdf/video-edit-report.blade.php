@extends('exports.pdf.layout')

@php
    /** Milliseconds are the storage unit; a report is read by a person. */
    $duration = static function (?int $ms): string {
        if ($ms === null) {
            return '—';
        }

        $seconds = intdiv($ms, 1000);

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    };
@endphp

@section('report_heading', 'AI edit report')
@section('report_subtitle', $edit['script_name']
    ? 'Analysed against “'.$edit['script_name'].'”.'
    : 'Analysed from the transcript. No script was attached.')

@section('content')
    <table class="data-table" style="margin-bottom: 18px;">
        <thead>
            <tr>
                <th>Original</th>
                <th>Final</th>
                <th>Removed</th>
                <th class="num">Cuts applied</th>
                <th class="num">Rejected</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $duration($edit['original_duration_ms']) }}</td>
                <td>{{ $duration($edit['final_duration_ms']) }}</td>
                <td>{{ $duration($edit['removed_duration_ms']) }}</td>
                <td class="num">{{ $edit['applied_cut_count'] }}</td>
                <td class="num">{{ $edit['rejected_decision_count'] }}</td>
            </tr>
        </tbody>
    </table>

    <h2 style="font-size: 13px; margin: 18px 0 6px 0;">Decisions</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Start</th>
                <th>End</th>
                <th>Reason</th>
                <th class="num">Confidence</th>
                <th>Outcome</th>
                <th>Evidence</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($decisions as $decision)
                <tr>
                    <td>{{ $duration($decision['start_ms']) }}</td>
                    <td>{{ $duration($decision['end_ms']) }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $decision['reason'])) }}</td>
                    <td class="num">{{ $decision['confidence'] === null ? '—' : round($decision['confidence'] * 100).'%' }}</td>
                    <td>
                        {{ ucfirst($decision['outcome']) }}
                        @if ($decision['rejection_reason'])
                            <br><span style="color:#6b7280;">{{ str_replace('_', ' ', $decision['rejection_reason']) }}</span>
                        @endif
                    </td>
                    <td>{{ $decision['evidence'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state">The AI proposed no cuts.</div></td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Advice, never actions: these were deliberately not applied (R6/R7). --}}
    <h2 style="font-size: 13px; margin: 20px 0 6px 0;">Recommendations — not applied</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Kind</th>
                <th>Finding</th>
                <th>Detail</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recommendations as $recommendation)
                <tr>
                    <td>{{ ucfirst(str_replace('_', ' ', $recommendation['kind'])) }}</td>
                    <td>{{ $recommendation['title'] }}</td>
                    <td style="text-align: left;">{{ $recommendation['detail'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3"><div class="empty-state">No recommendations.</div></td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($conclusion)
        <h2 style="font-size: 13px; margin: 20px 0 6px 0;">Conclusion</h2>
        <p style="margin: 0;">{{ $conclusion }}</p>
    @endif
@endsection
