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

        return [
            'run_id' => $run->id,
            'rules_version' => (int) $run->rules_version,
            'requirement_names' => StudioRequirementEloquentModel::query()
                ->where('user_id', $userId)
                ->whereHas('posting', $forProfile)
                ->pluck('canonical_name')
                ->map(static fn (mixed $name): string => (string) $name)
                ->values()
                ->all(),
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
