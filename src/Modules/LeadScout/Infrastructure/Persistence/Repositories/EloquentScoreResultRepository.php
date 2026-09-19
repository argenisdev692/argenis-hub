<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Domain\Entities\ScoreResult;
use Modules\LeadScout\Domain\Enums\DiscardReason;
use Modules\LeadScout\Domain\Enums\Tier;
use Modules\LeadScout\Domain\Ports\ScoreResultRepositoryPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutScoreReasonEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutScoreResultEloquentModel;

final readonly class EloquentScoreResultRepository implements ScoreResultRepositoryPort
{
    public function recordCurrent(
        int $companyId,
        ?int $profileId,
        string $rulesVersion,
        array $subscores,
        int $leadScore,
        int $confidence,
        Tier $tier,
        ?DiscardReason $discardReason,
        array $reasons,
    ): ScoreResult {
        return DB::transaction(static function () use (
            $companyId, $profileId, $rulesVersion, $subscores, $leadScore, $confidence, $tier, $discardReason, $reasons,
        ): ScoreResult {
            ScoutScoreResultEloquentModel::query()
                ->where('company_id', $companyId)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $result = ScoutScoreResultEloquentModel::query()->create([
                'company_id' => $companyId,
                'profile_id' => $profileId,
                'rules_version' => $rulesVersion,
                'subscores' => $subscores,
                'lead_score' => $leadScore,
                'confidence' => $confidence,
                'tier' => $tier->value,
                'discard_reason' => $discardReason?->value,
                'is_current' => true,
            ]);

            foreach ($reasons as $reason) {
                $result->reasons()->create($reason);
            }

            return self::toEntity($result->refresh());
        });
    }

    public function currentFor(int $companyId): ?ScoreResult
    {
        $result = ScoutScoreResultEloquentModel::query()
            ->where('company_id', $companyId)
            ->where('is_current', true)
            ->first();

        return $result === null ? null : self::toEntity($result);
    }

    public function currentTier(int $companyId): ?Tier
    {
        return ScoutScoreResultEloquentModel::query()
            ->where('company_id', $companyId)
            ->where('is_current', true)
            ->first(['id', 'tier'])
            ?->tier;
    }

    private static function toEntity(ScoutScoreResultEloquentModel $result): ScoreResult
    {
        $reasons = $result->reasons()
            ->with('signal:id,signal_key,evidence_url,evidence_excerpt')
            ->orderBy('id')
            ->get(['id', 'signal_id', 'points', 'explanation'])
            ->map(static fn (ScoutScoreReasonEloquentModel $reason): array => [
                'signal_key' => $reason->signal?->signal_key,
                'points' => (int) $reason->points,
                'explanation' => (string) $reason->explanation,
                'evidence_url' => $reason->signal?->evidence_url,
                'evidence_excerpt' => $reason->signal?->evidence_excerpt,
            ])
            ->values()
            ->all();

        return new ScoreResult(
            id: $result->id,
            uuid: $result->uuid,
            companyId: (int) $result->company_id,
            profileId: $result->profile_id,
            rulesVersion: (string) $result->rules_version,
            subscores: $result->subscores ?? [],
            leadScore: (int) $result->lead_score,
            confidence: (int) $result->confidence,
            tier: $result->tier,
            discardReason: $result->discard_reason,
            reasons: $reasons,
        );
    }
}
