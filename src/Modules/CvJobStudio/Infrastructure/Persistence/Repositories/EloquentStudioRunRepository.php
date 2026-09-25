<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\CvJobStudio\Application\DTOs\StudioInsightReportData;
use Modules\CvJobStudio\Application\DTOs\StudioRunData;
use Modules\CvJobStudio\Domain\Ports\StudioRunRepositoryPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioInsightReportEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRequirementEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioScoreEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Mappers\StudioReadModelMapper;

final readonly class EloquentStudioRunRepository implements StudioRunRepositoryPort
{
    public function runForUser(string $uuid, int $userId): ?StudioRunData
    {
        $run = StudioRunEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->first();

        return $run !== null ? StudioReadModelMapper::run($run) : null;
    }

    /**
     * One row per (posting, requirement) pair read in the profile's history,
     * not one row per distinct name: frequency is a property of how many
     * postings asked for a thing, so a deduplicated name list makes every
     * count 1 and every percentage meaningless. `covered` is not a guess
     * either — it is whether any stored score gave that requirement credit
     * above zero, which is the only evidence the CV carries it (FR-8).
     *
     * Two queries, both scoped to the profile: a frequency pass over the
     * requirement rows and a join for the covered names (BACKEND-PHP §4.1).
     */
    public function insightInputs(string $runUuid, int $userId): ?array
    {
        $run = StudioRunEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $runUuid)
            ->first(['id', 'profile_id', 'rules_version']);

        if ($run === null) {
            return null;
        }

        $forProfile = static fn ($posting) => $posting->where('profile_id', $run->profile_id);

        $rows = StudioRequirementEloquentModel::query()
            ->where('user_id', $userId)
            ->whereHas('posting', $forProfile)
            ->distinct()
            ->orderBy('posting_id')
            ->get(['posting_id', 'canonical_name']);

        $covered = DB::table('studio_skill_matches')
            ->join('studio_requirements', 'studio_requirements.id', '=', 'studio_skill_matches.requirement_id')
            ->join('studio_postings', 'studio_postings.id', '=', 'studio_requirements.posting_id')
            ->where('studio_requirements.user_id', $userId)
            ->where('studio_postings.profile_id', $run->profile_id)
            ->where('studio_skill_matches.credit_final', '>', 0)
            ->whereNull('studio_skill_matches.deleted_at')
            ->whereNull('studio_requirements.deleted_at')
            ->whereNull('studio_postings.deleted_at')
            ->distinct()
            ->pluck('studio_requirements.canonical_name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        $requirements = $rows->map(static fn (object $row): array => [
            'posting_id' => (int) $row->posting_id,
            'canonical_name' => (string) $row->canonical_name,
            'covered' => in_array((string) $row->canonical_name, $covered, true),
        ])->values()->all();

        return [
            'run_id' => $run->id,
            'rules_version' => (int) $run->rules_version,
            'jds_analyzed' => $rows->pluck('posting_id')->unique()->count(),
            'requirements' => $requirements,
            'scores' => StudioScoreEloquentModel::query()
                ->where('user_id', $userId)
                ->whereHas('posting', $forProfile)
                ->toBase()
                ->get(['cap_reason', 'raw_score', 'total_score'])
                ->map(static fn (object $score): array => [
                    'cap_reason' => $score->cap_reason,
                    'raw_score' => (float) $score->raw_score,
                    'total_score' => (float) $score->total_score,
                ])
                ->values()
                ->all(),
        ];
    }

    public function storeInsightReport(array $attributes): StudioInsightReportData
    {
        return DB::transaction(static fn (): StudioInsightReportData => StudioReadModelMapper::insightReport(
            StudioInsightReportEloquentModel::query()->create($attributes),
        ));
    }

    public function latestInsightReport(string $runUuid, int $userId): ?StudioInsightReportData
    {
        $report = StudioInsightReportEloquentModel::query()
            ->where('user_id', $userId)
            ->whereHas('run', static fn ($q) => $q->ownedBy($userId)->where('uuid', $runUuid))
            ->orderByDesc('id')
            ->first();

        return $report !== null ? StudioReadModelMapper::insightReport($report) : null;
    }
}
