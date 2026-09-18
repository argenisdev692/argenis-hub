<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\CvJobStudio\Domain\Ports\StudioScoreRepositoryPort;
use Modules\CvJobStudio\Domain\ValueObjects\ScoreBreakdown;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRequirementEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioScoreEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSkillMatchEloquentModel;

/**
 * Persists a score with its full breakdown + per-requirement matches in one
 * transaction, so SC-2 (offline recompute) and the reach table (FR-27) always
 * read a complete row set. Never modifies fit from outcomes (NFR-13).
 */
final readonly class EloquentStudioScoreRepository implements StudioScoreRepositoryPort
{
    public function store(string $postingUuid, int $userId, ScoreBreakdown $breakdown, string $postingTextHash): StudioScoreEloquentModel
    {
        return DB::transaction(function () use ($postingUuid, $userId, $breakdown, $postingTextHash): StudioScoreEloquentModel {
            $posting = StudioPostingEloquentModel::query()
                ->ownedBy($userId)
                ->where('uuid', $postingUuid)
                ->lockForUpdate()
                ->firstOrFail();

            $score = StudioScoreEloquentModel::query()->create([
                'user_id' => $userId,
                'posting_id' => $posting->id,
                'h' => $breakdown->h,
                's' => $breakdown->s,
                'd' => $breakdown->d,
                'h_components' => [
                    'matches' => $breakdown->matches,
                    'denominator' => $breakdown->denominator,
                    'posting_text_hash' => $postingTextHash,
                    'cap_context' => $breakdown->capContext,
                ],
                's_components' => $breakdown->sInputs,
                'd_components' => $breakdown->dInputs,
                'raw_score' => $breakdown->rawScore,
                'total_score' => $breakdown->totalScore,
                'band' => $breakdown->band->value,
                'caps_applied' => $breakdown->capsApplied,
                'cap_reason' => $breakdown->capReason,
                'rules_version' => $breakdown->rulesVersion,
                'computed_at' => now(),
            ]);

            foreach ($breakdown->matches as $match) {
                $requirement = StudioRequirementEloquentModel::query()
                    ->ownedBy($userId)
                    ->where('posting_id', $posting->id)
                    ->where('canonical_name', $match['requirement'])
                    ->first();

                if ($requirement === null) {
                    continue;
                }

                StudioSkillMatchEloquentModel::query()->create([
                    'user_id' => $userId,
                    'score_id' => $score->id,
                    'requirement_id' => $requirement->id,
                    'credit_base' => $match['credit_base'],
                    'context_factor' => $match['context_factor'],
                    'position_factor' => $match['position_factor'],
                    'credit_final' => $match['credit_final'],
                ]);
            }

            return $score;
        });
    }

    public function latestForPosting(string $postingUuid, int $userId): ?StudioScoreEloquentModel
    {
        return StudioScoreEloquentModel::query()
            ->where('user_id', $userId)
            ->whereHas('posting', static fn ($q): mixed => $q->ownedBy($userId)->where('uuid', $postingUuid))
            ->with('skillMatches')
            ->orderByDesc('computed_at')
            ->orderByDesc('id')
            ->first();
    }
}
