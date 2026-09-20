<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\CvJobStudio\Application\DTOs\StudioScoreData;
use Modules\CvJobStudio\Domain\Ports\StudioScoreRepositoryPort;
use Modules\CvJobStudio\Domain\ValueObjects\ScoreBreakdown;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRequirementEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioScoreEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSkillMatchEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Mappers\StudioReadModelMapper;

/**
 * Persists a score with its full breakdown + per-requirement matches in one
 * transaction, so SC-2 (offline recompute) and the reach table (FR-27) always
 * read a complete row set. Never modifies fit from outcomes (NFR-13).
 */
final readonly class EloquentStudioScoreRepository implements StudioScoreRepositoryPort
{
    public function store(string $postingUuid, int $userId, ScoreBreakdown $breakdown, string $postingTextHash): StudioScoreData
    {
        return DB::transaction(static function () use ($postingUuid, $userId, $breakdown, $postingTextHash): StudioScoreData {
            $posting = StudioPostingEloquentModel::query()
                ->ownedBy($userId)
                ->where('uuid', $postingUuid)
                ->lockForUpdate()
                ->firstOrFail(['id']);

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

            // One lookup for every match instead of one query per match.
            $requirementIds = StudioRequirementEloquentModel::query()
                ->ownedBy($userId)
                ->where('posting_id', $posting->id)
                ->pluck('id', 'canonical_name');

            foreach ($breakdown->matches as $match) {
                $requirementId = $requirementIds[$match['requirement']] ?? null;

                if ($requirementId === null) {
                    continue;
                }

                StudioSkillMatchEloquentModel::query()->create([
                    'user_id' => $userId,
                    'score_id' => $score->id,
                    'requirement_id' => $requirementId,
                    'credit_base' => $match['credit_base'],
                    'context_factor' => $match['context_factor'],
                    'position_factor' => $match['position_factor'],
                    'credit_final' => $match['credit_final'],
                ]);
            }

            return StudioReadModelMapper::score($score->refresh());
        });
    }

    public function latestComponents(string $postingUuid, int $userId): ?array
    {
        $score = StudioScoreEloquentModel::query()
            ->where('user_id', $userId)
            ->whereHas('posting', static fn ($q): mixed => $q->ownedBy($userId)->where('uuid', $postingUuid))
            ->orderByDesc('computed_at')
            ->orderByDesc('id')
            ->first(['id', 'h_components', 's', 'd', 'total_score']);

        if ($score === null) {
            return null;
        }

        return [
            'h_components' => (array) ($score->h_components ?? []),
            's' => (float) $score->s,
            'd' => (float) $score->d,
            'total_score' => (float) $score->total_score,
        ];
    }

    public function narrationBreakdown(string $scoreUuid, int $userId): ?array
    {
        $score = StudioScoreEloquentModel::query()
            ->where('user_id', $userId)
            ->where('uuid', $scoreUuid)
            ->with(['skillMatches:id,score_id,requirement_id,credit_final', 'skillMatches.requirement:id,canonical_name'])
            ->first(['id', 'total_score', 'band']);

        if ($score === null) {
            return null;
        }

        return [
            'total' => (float) $score->total_score,
            'band' => $score->band,
            'matches' => $score->skillMatches->map(static fn ($match): array => [
                'requirement' => (string) $match->requirement?->canonical_name,
                'credit' => (float) $match->credit_final,
            ])->values()->all(),
        ];
    }

    /**
     * Everything the opportunity calculator reads, eager-loaded in one pass:
     * profile rules, a sightings count and only the newest JD text — never
     * one query per score (BACKEND-PHP §4.1).
     */
    public function priorityCandidates(int $userId, DateTimeImmutable $staleBefore): array
    {
        return StudioScoreEloquentModel::query()
            ->where('user_id', $userId)
            ->where(static function ($query) use ($staleBefore): void {
                $query->whereNull('priority_computed_at')
                    ->orWhere('priority_computed_at', '<=', $staleBefore);
            })
            ->with(['posting' => static fn ($posting) => $posting
                ->select(['id', 'profile_id', 'title', 'discovery_channel', 'posted_at'])
                ->withCount('sightings')
                ->with([
                    'profile:id,rules',
                    'texts' => static fn ($texts) => $texts->select(['id', 'posting_id', 'text'])->latest('id')->limit(1),
                ])])
            ->lockForUpdate()
            ->get(['id', 'posting_id', 'total_score'])
            ->filter(static fn (StudioScoreEloquentModel $score): bool => $score->posting !== null)
            ->map(static fn (StudioScoreEloquentModel $score): array => [
                'score_id' => $score->id,
                'total_score' => (float) $score->total_score,
                'opportunity_rules' => $score->posting->profile?->rules['opportunity'] ?? null,
                'channel' => $score->posting->discovery_channel,
                'posted_at' => $score->posting->posted_at,
                'relist_count' => (int) $score->posting->sightings_count,
                'text' => (string) $score->posting->texts->first()?->text,
                'title' => (string) $score->posting->title,
            ])
            ->values()
            ->all();
    }

    /** One read for every score, then one write per row (casts need the model). */
    public function recordPriorities(array $priorities): void
    {
        if ($priorities === []) {
            return;
        }

        StudioScoreEloquentModel::query()
            ->whereKey(array_keys($priorities))
            ->get(['id'])
            ->each(static fn (StudioScoreEloquentModel $score): bool => $score->update([
                'o_components' => $priorities[$score->id]['components'],
                'opportunity_static' => $priorities[$score->id]['factor'],
                'apply_priority' => $priorities[$score->id]['priority'],
                'priority_computed_at' => now(),
            ]));
    }
}
