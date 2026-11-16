<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Persistence\Repositories;

use Modules\VideoEdits\Domain\Enums\AiRecommendationKind;
use Modules\VideoEdits\Domain\Ports\AiReportStorePort;
use Modules\VideoEdits\Domain\ValueObjects\AiAnalysis;
use Modules\VideoEdits\Domain\ValueObjects\AiRecommendation;
use Modules\VideoEdits\Domain\ValueObjects\DurationPlan;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;

/**
 * The advisory half of an AI edit, stored on the edit itself (US-14).
 *
 * A JSON column rather than a table because the report is always read whole and
 * never queried by field — a `video_edit_ai_recommendations` table would buy
 * joins nobody makes. It also means the report disappears with the edit row.
 *
 * Only validated recommendations, the conclusion, the measured duration plan and
 * the phrases the request protected are written; the raw provider response is
 * deliberately never persisted (decision R10).
 */
final readonly class EloquentAiReportStore implements AiReportStorePort
{
    public function store(int $videoEditId, AiAnalysis $analysis): void
    {
        VideoEditEloquentModel::query()
            ->whereKey($videoEditId)
            ->update(['ai_report' => [
                'recommendations' => array_map(
                    static fn (AiRecommendation $recommendation): array => $recommendation->toArray(),
                    $analysis->recommendations,
                ),
                'conclusion' => $analysis->conclusion,
                // Measured, not generated: the target arithmetic and the
                // owner's never-cut phrases belong beside the findings they
                // explain, so a second copy of the report cannot drift.
                'duration_plan' => $analysis->durationPlan?->toArray(),
                'kept_phrases' => array_values($analysis->keptPhrases),
            ]]);
    }

    /**
     * Merges the plan into the report the producer already wrote, instead of
     * replacing it: the recommendations and the conclusion are the model's, the
     * plan is the pipeline's, and neither may erase the other.
     */
    public function storeDurationPlan(int $videoEditId, ?DurationPlan $plan): void
    {
        $report = VideoEditEloquentModel::query()->whereKey($videoEditId)->value('ai_report');
        $payload = is_array($report) ? $report : [];
        $payload['duration_plan'] = $plan?->toArray();

        VideoEditEloquentModel::query()
            ->whereKey($videoEditId)
            ->update(['ai_report' => $payload]);
    }

    public function forEdit(int $videoEditId): ?AiAnalysis
    {
        $report = VideoEditEloquentModel::query()->whereKey($videoEditId)->value('ai_report');

        if (! is_array($report)) {
            return null;
        }

        $rows = is_array($report['recommendations'] ?? null) ? $report['recommendations'] : [];
        $plan = is_array($report['duration_plan'] ?? null) ? DurationPlan::fromArray($report['duration_plan']) : null;

        return new AiAnalysis(
            recommendations: array_values(array_filter(array_map(
                static function (mixed $row): ?AiRecommendation {
                    if (! is_array($row)) {
                        return null;
                    }

                    $kind = AiRecommendationKind::tryFrom((string) ($row['kind'] ?? ''));

                    return $kind === null ? null : new AiRecommendation(
                        kind: $kind,
                        title: (string) ($row['title'] ?? ''),
                        detail: (string) ($row['detail'] ?? ''),
                        startMs: isset($row['start_ms']) ? (int) $row['start_ms'] : null,
                        endMs: isset($row['end_ms']) ? (int) $row['end_ms'] : null,
                    );
                },
                $rows,
            ))),
            conclusion: isset($report['conclusion']) ? (string) $report['conclusion'] : null,
            durationPlan: $plan,
            keptPhrases: array_values(array_filter(
                is_array($report['kept_phrases'] ?? null) ? $report['kept_phrases'] : [],
                is_string(...),
            )),
        );
    }
}
