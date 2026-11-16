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

    @if (! empty($durationPlan))
        @php
            $plan = $durationPlan;
            $verdict = match ($plan['verdict']) {
                'no_target' => 'No target duration was requested; this is the gap inventory.',
                'target_met' => 'The recording is already at or under the target.',
                'reachable' => 'The measured removals reach the target.',
                default => 'The measured removals do not reach the target; the shortfall has to come from a re-record or from cuts nobody has proposed yet.',
            };
        @endphp

        {{-- The subtraction the target implies. Every number is measured from
             the word timings; none of it was estimated by the model. --}}
        <h2 style="font-size: 13px; margin: 18px 0 6px 0;">Duration plan</h2>
        <table class="data-table" style="margin-bottom: 8px;">
            <thead>
                <tr>
                    <th>Recording</th>
                    <th>Target</th>
                    <th>Must remove</th>
                    <th>Dead air</th>
                    <th>Silence</th>
                    <th>Cuts</th>
                    <th>Projected</th>
                    <th>Shortfall</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $duration($plan['total_ms']) }}</td>
                    <td>{{ $plan['target_ms'] === null ? '—' : $duration($plan['target_ms']) }}</td>
                    <td>{{ $plan['required_removal_ms'] === null ? '—' : $duration($plan['required_removal_ms']) }}</td>
                    <td>{{ $duration($plan['dead_air_ms']) }} ({{ $plan['gap_count'] }} gaps)</td>
                    <td>{{ $duration($plan['silence_recoverable_ms']) }}</td>
                    <td>{{ $duration($plan['planned_cut_ms'] ?? (($plan['speech_cut_ms'] ?? 0) + ($plan['ai_proposal_ms'] ?? 0) + ($plan['manual_cut_ms'] ?? 0))) }}</td>
                    <td>{{ $duration($plan['projected_ms']) }}</td>
                    <td>{{ $plan['shortfall_ms'] === null ? '—' : $duration($plan['shortfall_ms']) }}</td>
                </tr>
            </tbody>
        </table>
        <p style="margin: 0 0 4px 0; color:#374151;">{{ $verdict }}</p>
        <p style="margin: 0 0 4px 0; color:#6b7280;">
            Cuts split: speech cleanup {{ $duration($plan['speech_cut_ms'] ?? 0) }},
            AI {{ $duration($plan['ai_proposal_ms'] ?? 0) }},
            manual {{ $duration($plan['manual_cut_ms'] ?? 0) }}.
        </p>
        <p style="margin: 0 0 4px 0; color:#6b7280;">
            Dead air split: {{ $duration($plan['sentence_gap_ms']) }} after a finished sentence,
            {{ $duration($plan['intra_phrase_gap_ms']) }} inside a phrase.
        </p>

        @if (! empty($plan['longest_gaps']))
            <table class="data-table" style="margin-top: 8px;">
                <thead>
                    <tr>
                        <th>Longest gaps</th>
                        <th>At</th>
                        <th class="num">Length</th>
                        <th>Kind</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($plan['longest_gaps'] as $gap)
                        <tr>
                            <td>—</td>
                            <td>{{ $duration($gap['start_ms']) }}</td>
                            <td class="num">{{ $duration($gap['gap_ms']) }}</td>
                            <td>{{ $gap['after_sentence_end'] ? 'After a sentence' : 'Inside a phrase' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (! empty($keptPhrases))
            <p style="margin: 8px 0 0 0; color:#6b7280;">
                Kept by request, so cuts containing them were not proposed:
                {{ implode(', ', $keptPhrases) }}.
            </p>
        @endif
    @endif

    {{-- The same rows, in the same order, as the review screen. The PDF is the
         copy the owner keeps, so a cut that was on screen and missing here
         would make the document useless as a record. --}}
    @if (! empty($reviewedCuts))
        <h2 style="font-size: 13px; margin: 22px 0 6px 0;">Reviewed cuts</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Start</th>
                    <th>End</th>
                    <th>Origin</th>
                    <th>Motivo</th>
                    <th>Removed</th>
                    <th>Kept</th>
                    <th>Approved</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reviewedCuts as $cut)
                    <tr>
                        <td>{{ $duration($cut['start_ms']) }}</td>
                        <td>{{ $duration($cut['end_ms']) }}</td>
                        <td>{{ $cut['origin'] === 'ai' ? 'AI' : 'Automatic' }}</td>
                        <td style="text-align: left;">
                            {{ ucfirst(str_replace('_', ' ', $cut['reason'])) }}
                            @if ($cut['explanation'])
                                <br><span style="color:#6b7280;">{{ $cut['explanation'] }}</span>
                            @endif
                        </td>
                        <td style="text-align: left;">{{ $cut['text'] !== '' ? $cut['text'] : '—' }}</td>
                        <td style="text-align: left;">{{ $cut['keep_text'] ?? '—' }}</td>
                        <td>{{ $cut['approved'] ? 'Yes' : 'No' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

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
                <th>When</th>
                <th>Finding</th>
                <th>Detail</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recommendations as $recommendation)
                @php
                    // Several kinds carry a time range, and the range is the point:
                    // "knowing it happened is useless without knowing where", which
                    // is what makes a re-record aimable. It was carried on the DTO
                    // and dropped here, so every finding read as a statistic
                    // instead of a place to scrub to.
                    $from = $recommendation['start_ms'] ?? null;
                    $to = $recommendation['end_ms'] ?? null;
                    $timecode = $from === null
                        ? '—'
                        : ($to === null ? sprintf('%d:%02d', intdiv($from / 1000, 60), ($from / 1000) % 60)
                            : sprintf('%d:%02d–%d:%02d', intdiv($from / 1000, 60), ($from / 1000) % 60, intdiv($to / 1000, 60), ($to / 1000) % 60));
                @endphp
                <tr>
                    <td>{{ ucfirst(str_replace('_', ' ', $recommendation['kind'])) }}</td>
                    <td>{{ $timecode }}</td>
                    <td>{{ $recommendation['title'] }}</td>
                    <td style="text-align: left;">{{ $recommendation['detail'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty-state">No recommendations.</div></td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($conclusion)
        <h2 style="font-size: 13px; margin: 20px 0 6px 0;">Conclusion</h2>
        <p style="margin: 0;">{{ $conclusion }}</p>
    @endif
@endsection
