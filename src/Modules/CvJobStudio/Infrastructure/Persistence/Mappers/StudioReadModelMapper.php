<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Mappers;

use Modules\CvJobStudio\Application\DTOs\StudioCvAuditData;
use Modules\CvJobStudio\Application\DTOs\StudioCvVersionData;
use Modules\CvJobStudio\Application\DTOs\StudioInsightReportData;
use Modules\CvJobStudio\Application\DTOs\StudioPostingData;
use Modules\CvJobStudio\Application\DTOs\StudioPostingDetailData;
use Modules\CvJobStudio\Application\DTOs\StudioProfileData;
use Modules\CvJobStudio\Application\DTOs\StudioRunData;
use Modules\CvJobStudio\Application\DTOs\StudioScoreData;
use Modules\CvJobStudio\Domain\ValueObjects\CvStructureSnapshot;
use Modules\CvJobStudio\Domain\ValueObjects\CvVersionSnapshot;
use Modules\CvJobStudio\Domain\ValueObjects\PostingScoringContext;
use Modules\CvJobStudio\Domain\ValueObjects\StudioProfileSnapshot;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvAuditEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvStructureEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvVersionEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioInsightReportEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioScoreEloquentModel;

/**
 * The ONLY bridge between Studio Eloquent rows and the read models / domain
 * snapshots handlers see (Mapper rule: the read shapes fold in the latest
 * score, relations and casts, so they diverge from the tables). Repositories
 * call it; Application code never touches a model.
 */
final readonly class StudioReadModelMapper
{
    /** Reads `scores->first()` as the latest score — the relation orders newest first. */
    #[\NoDiscard]
    public static function posting(StudioPostingEloquentModel $posting): StudioPostingData
    {
        $latest = $posting->relationLoaded('scores') ? $posting->scores->first() : null;

        return new StudioPostingData(
            uuid: $posting->uuid,
            title: $posting->title,
            employerName: $posting->employer_name,
            locationText: $posting->location_text,
            remoteScope: $posting->remote_scope,
            status: $posting->status,
            discoveryChannel: $posting->discovery_channel,
            applyDestination: $posting->apply_destination,
            totalScore: $latest !== null ? (float) $latest->total_score : null,
            band: $latest?->band,
            capReason: $latest?->cap_reason,
            attribution: $posting->attribution,
            canonicalUrl: $posting->canonical_url,
            createdAt: $posting->created_at?->toIso8601String(),
            deletedAt: $posting->deleted_at?->toIso8601String(),
        );
    }

    #[\NoDiscard]
    public static function postingDetail(StudioPostingEloquentModel $posting): StudioPostingDetailData
    {
        $latest = $posting->scores->first();

        return new StudioPostingDetailData(
            posting: self::posting($posting),
            requirements: $posting->requirements->map(static fn ($requirement): array => [
                'uuid' => $requirement->uuid,
                'canonical_name' => $requirement->canonical_name,
                'tag' => $requirement->tag->value,
                'nature' => $requirement->nature->value,
            ])->values()->all(),
            gates: $posting->gateResults->map(static fn ($gate): array => [
                'gate_code' => $gate->gate_code,
                'passed' => (bool) $gate->passed,
                'reason_code' => $gate->reason_code,
            ])->values()->all(),
            score: $latest !== null ? self::score($latest) : null,
        );
    }

    #[\NoDiscard]
    public static function score(StudioScoreEloquentModel $score): StudioScoreData
    {
        return new StudioScoreData(
            uuid: $score->uuid,
            h: (float) $score->h,
            s: (float) $score->s,
            d: (float) $score->d,
            rawScore: (float) $score->raw_score,
            totalScore: (float) $score->total_score,
            band: $score->band,
            capReason: $score->cap_reason,
            rulesVersion: $score->rules_version,
            heuristicLabel: 'Heuristic estimate — not a vendor ATS score.',
            computedAt: $score->computed_at?->toIso8601String(),
        );
    }

    #[\NoDiscard]
    public static function profile(StudioProfileEloquentModel $profile): StudioProfileData
    {
        return new StudioProfileData(
            name: $profile->name,
            slug: $profile->slug,
            baseCity: $profile->base_city,
            baseCountry: $profile->base_country,
            acceptedRemoteScopes: $profile->accepted_remote_scopes,
            stackMust: $profile->stack_must,
            stackReject: $profile->stack_reject,
            skillFloors: self::skillFloors($profile->skill_floors ?? []),
            geographyDeny: $profile->geography_deny,
            yearsBaseline: $profile->years_baseline,
            uuid: $profile->uuid,
        );
    }

    #[\NoDiscard]
    public static function profileSnapshot(StudioProfileEloquentModel $profile): StudioProfileSnapshot
    {
        return new StudioProfileSnapshot(
            id: $profile->id,
            uuid: $profile->uuid,
            acceptedRemoteScopes: array_values($profile->accepted_remote_scopes ?? []),
            stackMust: array_values($profile->stack_must ?? []),
            stackReject: array_values($profile->stack_reject ?? []),
            skillFloors: self::skillFloors($profile->skill_floors ?? []),
            rules: (array) ($profile->rules ?? []),
        );
    }

    /**
     * Levels arrive from the form as ints, from JSON as whatever `json_decode`
     * produced. Anything outside 0-4 is dropped rather than clamped: a level of
     * 9 is a mistake, and silently turning it into 4 would make the filter
     * block postings the operator never asked it to block.
     *
     * @return array<string, int>
     */
    private static function skillFloors(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($raw)) {
            return [];
        }

        $floors = [];

        foreach ($raw as $skill => $level) {
            $skill = trim((string) $skill);
            $level = is_numeric($level) ? (int) $level : -1;

            if ($skill !== '' && $level >= 0 && $level <= 4) {
                $floors[$skill] = $level;
            }
        }

        return $floors;
    }

    #[\NoDiscard]
    public static function run(StudioRunEloquentModel $run): StudioRunData
    {
        return new StudioRunData(
            uuid: $run->uuid,
            status: $run->status,
            startedAt: $run->started_at?->toIso8601String(),
            finishedAt: $run->finished_at?->toIso8601String(),
            candidatesCount: (int) $run->candidates_count,
            gatePassedCount: (int) $run->gate_passed_count,
            extractedCount: (int) $run->extracted_count,
            scoredCount: (int) $run->scored_count,
            newMatchesCount: (int) $run->new_matches_count,
            spendMicros: (int) $run->spend_micros,
        );
    }

    /** Expects `profile`, `requirements`, `gateResults` and the newest `texts` row eager-loaded. */
    #[\NoDiscard]
    public static function scoringContext(StudioPostingEloquentModel $posting): PostingScoringContext
    {
        return new PostingScoringContext(
            id: $posting->id,
            uuid: $posting->uuid,
            rules: $posting->profile?->rules,
            rulesVersion: (int) ($posting->profile?->rules_version ?? 0),
            requirements: $posting->requirements->map(static fn ($requirement): array => [
                'uuid' => $requirement->uuid,
                'canonical_name' => (string) $requirement->canonical_name,
                'raw_text' => (string) $requirement->raw_text,
                'tag' => $requirement->tag,
                'nature' => $requirement->nature,
            ])->values()->all(),
            gates: $posting->gateResults->map(static fn ($gate): array => [
                'gate_code' => (string) $gate->gate_code,
                'passed' => (bool) $gate->passed,
            ])->values()->all(),
            latestText: (string) $posting->texts->first()?->text,
        );
    }

    #[\NoDiscard]
    public static function cvVersion(StudioCvVersionEloquentModel $version): StudioCvVersionData
    {
        return new StudioCvVersionData(
            uuid: $version->uuid,
            purpose: $version->purpose,
            language: $version->language,
            createdAt: $version->created_at?->toIso8601String(),
        );
    }

    #[\NoDiscard]
    public static function cvVersionSnapshot(StudioCvVersionEloquentModel $version): CvVersionSnapshot
    {
        return new CvVersionSnapshot(
            id: $version->id,
            uuid: $version->uuid,
            cvId: (int) $version->cv_id,
            structureId: $version->structure_id,
            profileId: $version->profile_id,
            postingId: $version->posting_id,
            purpose: $version->purpose,
            language: $version->language,
            content: (array) $version->content,
        );
    }

    /** Expects `entries.bullets` and `skills` eager-loaded. */
    #[\NoDiscard]
    public static function cvStructureSnapshot(StudioCvStructureEloquentModel $structure): CvStructureSnapshot
    {
        return new CvStructureSnapshot(
            id: $structure->id,
            uuid: $structure->uuid,
            cvId: (int) $structure->cv_id,
            isConfirmed: $structure->confirmed_at !== null,
            profileFacts: (array) $structure->profile_facts,
            snapshot: [
                'entries' => $structure->entries->map(static fn ($entry): array => [
                    'ordinal' => (int) $entry->ordinal,
                    'organization' => $entry->organization,
                    'role_title' => $entry->role_title,
                ])->values()->all(),
                'skills' => $structure->skills->map(static fn ($skill): array => [
                    'canonical_name' => (string) $skill->canonical_name,
                    'evidence' => (string) $skill->evidence,
                ])->values()->all(),
            ],
            protectedBullets: $structure->entries
                ->where('is_protected', true)
                ->flatMap(static fn ($entry) => $entry->bullets->map(static fn ($bullet): string => (string) $bullet->text))
                ->values()
                ->all(),
            document: $structure->toArray(),
        );
    }

    #[\NoDiscard]
    public static function cvAudit(StudioCvAuditEloquentModel $audit): StudioCvAuditData
    {
        return new StudioCvAuditData(
            uuid: $audit->uuid,
            verdict: $audit->verdict,
            verdictReasons: $audit->verdict_reasons,
            targetJobTitle: $audit->target_job_title,
            strengths: $audit->strengths,
            improvements: $audit->improvements,
            keywordGaps: $audit->keyword_gaps,
            xyzGaps: $audit->xyz_gaps,
            createdAt: $audit->created_at?->toIso8601String(),
        );
    }

    #[\NoDiscard]
    public static function insightReport(StudioInsightReportEloquentModel $report): StudioInsightReportData
    {
        return new StudioInsightReportData(
            uuid: $report->uuid,
            jdsAnalyzed: (int) $report->jds_analyzed,
            sampleNote: $report->sample_note,
            requirements: (array) $report->requirements,
            reachTable: array_values((array) $report->reach_table),
            recommendations: array_values((array) $report->recommendations),
            outcomeCorrelation: (array) $report->outcome_correlation,
            rulesVersion: (int) $report->rules_version,
            createdAt: $report->created_at?->toIso8601String(),
        );
    }
}
