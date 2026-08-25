@php
    $scores = $campaign->scores ?? [];
    $scoreRows = [
        ['key' => 'audience_fit_score', 'label' => 'Audience Fit', 'critical' => true, 'value' => $campaign->audience_fit_score],
        ['key' => 'virality_score', 'label' => 'Virality Probability', 'critical' => false, 'value' => $campaign->virality_score],
        ['key' => 'roi_potential_score', 'label' => 'ROI Potential', 'critical' => false, 'value' => $campaign->roi_potential_score],
        ['key' => 'lead_quality_score', 'label' => 'Lead Quality', 'critical' => false, 'value' => $campaign->lead_quality_score],
        ['key' => 'trend_relevance_score', 'label' => 'Trend Relevance', 'critical' => false, 'value' => $campaign->trend_relevance_score],
    ];
    $probabilityColor = match ($campaign->success_probability_label) {
        'very_high' => '#15803d',
        'high' => '#7c3aed',
        'medium' => '#b45309',
        'low' => '#b91c1c',
        default => '#9ca3af',
    };
    $stageColor = match ($campaign->funnel_stage?->value) {
        'tofu' => '#2563eb',
        'mofu' => '#0d9488',
        'bofu' => '#ea580c',
        'loyalty' => '#16a34a',
        default => '#6b7280',
    };
@endphp
@extends('exports.pdf.layout')

@section('report_heading', 'Campaign Report')
@section('report_subtitle', $campaign->topic)

@section('content')
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px;">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr><td style="padding: 3px 0; color: #9ca3af; font-size: 8.5px; text-transform: uppercase;">Niche</td></tr>
                    <tr><td style="padding: 0 0 8px 0; font-size: 10.5px;">{{ $campaign->niche ?? '—' }}</td></tr>
                    <tr><td style="padding: 3px 0; color: #9ca3af; font-size: 8.5px; text-transform: uppercase;">Audience</td></tr>
                    <tr><td style="padding: 0 0 8px 0; font-size: 10.5px;">{{ $campaign->audience ?? '—' }}</td></tr>
                    <tr><td style="padding: 3px 0; color: #9ca3af; font-size: 8.5px; text-transform: uppercase;">Business Goal / Brand Voice</td></tr>
                    <tr><td style="padding: 0 0 8px 0; font-size: 10.5px;">{{ ucfirst($campaign->business_goal->value) }} · {{ ucfirst($campaign->brand_voice->value) }}</td></tr>
                </table>
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 10px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 3px 0;">
                            <span style="background-color: {{ $stageColor }}; color: #fff; font-size: 8.5px; font-weight: bold; padding: 3px 8px; border-radius: 3px; text-transform: uppercase;">{{ $campaign->funnel_stage->value }}</span>
                            <span style="margin-left: 6px; font-size: 9.5px; color: #4b5563;">{{ ucfirst($campaign->platform->value) }} · {{ str_replace('_', ' ', ucfirst($campaign->ad_format->value)) }}</span>
                        </td>
                    </tr>
                    <tr><td style="padding: 8px 0 3px 0; color: #9ca3af; font-size: 8.5px; text-transform: uppercase;">Status</td></tr>
                    <tr><td style="padding: 0 0 8px 0; font-size: 10.5px;">{{ ucfirst(str_replace('_', ' ', $campaign->status->value)) }} · Provider: {{ $campaign->provider }} · Iterations: {{ $campaign->iterations_required ?? '—' }}</td></tr>
                    @if ($campaign->quality_warning)
                        <tr><td style="padding: 4px 6px; background-color: #fef3c7; color: #92400e; font-size: 9px; border-radius: 3px;">⚠ {{ $campaign->quality_warning_message }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- Success probability callout --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px; border: 1.5px solid {{ $probabilityColor }}; border-radius: 4px;">
        <tr>
            <td style="padding: 10px 14px; width: 60%;">
                <div style="color: #9ca3af; font-size: 8px; font-weight: bold; letter-spacing: .1em; text-transform: uppercase;">Success Probability</div>
                <div style="color: {{ $probabilityColor }}; font-size: 20px; font-weight: bold;">{{ $campaign->overall_score_avg !== null ? $campaign->overall_score_avg.'%' : '—' }}
                    <span style="font-size: 11px; text-transform: uppercase;">({{ $campaign->success_probability_label ? str_replace('_', ' ', $campaign->success_probability_label) : 'pending' }})</span>
                </div>
            </td>
            <td style="padding: 10px 14px; text-align: right; vertical-align: middle;">
                <span style="font-size: 10px; color: {{ $campaign->all_scores_pass ? '#15803d' : '#b91c1c' }}; font-weight: bold;">
                    {{ $campaign->all_scores_pass ? '✓ All thresholds passed' : '✗ Below threshold — needs review' }}
                </span>
            </td>
        </tr>
    </table>

    {{-- Score breakdown --}}
    <table class="data-table" style="margin-bottom: 14px;">
        <thead>
            <tr>
                <th>Score</th>
                <th class="num">Value</th>
                <th class="num">Threshold</th>
                <th class="num">Pass</th>
                <th>Explanation</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($scoreRows as $row)
                <tr>
                    <td>{{ $row['label'] }}{{ $row['critical'] ? ' (critical)' : '' }}</td>
                    <td class="num">{{ $row['value'] ?? '—' }}</td>
                    <td class="num">{{ $scores[$row['key']]['threshold'] ?? '—' }}</td>
                    <td class="num">{{ isset($scores[$row['key']]['passes']) ? ($scores[$row['key']]['passes'] ? 'Yes' : 'No') : '—' }}</td>
                    <td>{{ $scores[$row['key']]['explanation'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Meta Ads copy --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px;">
        <tr><td style="color: #5b21b6; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .06em; padding-bottom: 4px;">Meta Ads Copy</td></tr>
        <tr><td style="padding: 3px 0; color: #9ca3af; font-size: 8.5px; text-transform: uppercase;">Headline</td></tr>
        <tr><td style="padding: 0 0 6px 0; font-size: 11px; font-weight: bold;">{{ $campaign->headline ?? '—' }}</td></tr>
        <tr><td style="padding: 3px 0; color: #9ca3af; font-size: 8.5px; text-transform: uppercase;">Primary Text</td></tr>
        <tr><td style="padding: 0 0 6px 0; font-size: 10px;">{{ $campaign->primary_text ?? '—' }}</td></tr>
        @if ($campaign->description)
            <tr><td style="padding: 3px 0; color: #9ca3af; font-size: 8.5px; text-transform: uppercase;">Description</td></tr>
            <tr><td style="padding: 0 0 6px 0; font-size: 10px;">{{ $campaign->description }}</td></tr>
        @endif
        <tr><td style="padding: 3px 0; color: #9ca3af; font-size: 8.5px; text-transform: uppercase;">Call To Action</td></tr>
        <tr><td style="padding: 0 0 6px 0; font-size: 10px; font-weight: bold; color: #7c3aed;">{{ $campaign->call_to_action ?? '—' }}</td></tr>
        @if (! empty($campaign->hashtags))
            <tr><td style="font-size: 9.5px; color: #6b7280;">{{ implode(' ', $campaign->hashtags) }}</td></tr>
        @endif
    </table>

    {{-- Per-platform variants --}}
    @if (! empty($campaign->platforms))
        <table class="data-table" style="margin-bottom: 14px;">
            <thead>
                <tr>
                    <th>Platform</th>
                    <th>Headline</th>
                    <th>Adapted Primary Text</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($campaign->platforms as $platform => $variant)
                    <tr>
                        <td>{{ ucfirst($platform) }}</td>
                        <td>{{ $variant['headline'] ?? '—' }}</td>
                        <td>{{ $variant['adapted_primary_text'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Lead form questions + targeting suggestions --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px;">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                <div style="color: #5b21b6; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .06em; padding-bottom: 4px;">Lead Form Questions</div>
                @forelse ($campaign->lead_form_questions ?? [] as $question)
                    <div style="font-size: 9.5px; padding: 2px 0;">• {{ $question }}</div>
                @empty
                    <div style="font-size: 9.5px; color: #9ca3af;">—</div>
                @endforelse
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 10px;">
                <div style="color: #5b21b6; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .06em; padding-bottom: 4px;">Targeting Suggestions</div>
                @forelse ($campaign->targeting_suggestions ?? [] as $suggestion)
                    <div style="font-size: 9.5px; padding: 2px 0;">• {{ $suggestion }}</div>
                @empty
                    <div style="font-size: 9.5px; color: #9ca3af;">—</div>
                @endforelse
            </td>
        </tr>
    </table>

    {{-- Optimization suggestions --}}
    @if (! empty($campaign->optimization_suggestions))
        <table style="width: 100%; border-collapse: collapse;">
            <tr><td style="color: #5b21b6; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .06em; padding-bottom: 4px;">Optimization Suggestions</td></tr>
            @foreach ($campaign->optimization_suggestions as $suggestion)
                <tr><td style="font-size: 9.5px; padding: 2px 0;">• {{ $suggestion }}</td></tr>
            @endforeach
        </table>
    @endif
@endsection
