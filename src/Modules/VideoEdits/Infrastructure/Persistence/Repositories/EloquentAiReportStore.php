<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Persistence\Repositories;

use Modules\VideoEdits\Domain\Enums\AiRecommendationKind;
use Modules\VideoEdits\Domain\Ports\AiReportStorePort;
use Modules\VideoEdits\Domain\ValueObjects\AiAnalysis;
use Modules\VideoEdits\Domain\ValueObjects\AiRecommendation;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;

/**
 * The advisory half of an AI edit, stored on the edit itself (US-14).
 *
 * A JSON column rather than a table because the report is always read whole and
 * never queried by field — a `video_edit_ai_recommendations` table would buy
 * joins nobody makes. It also means the report disappears with the edit row.
 *
 * Only validated recommendations and the conclusion are written; the raw
 * provider response is deliberately never persisted (decision R10).
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
            ]]);
    }

    public function forEdit(int $videoEditId): ?AiAnalysis
    {
        $report = VideoEditEloquentModel::query()->whereKey($videoEditId)->value('ai_report');

        if (! is_array($report)) {
            return null;
        }

        $rows = is_array($report['recommendations'] ?? null) ? $report['recommendations'] : [];

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
        );
    }
}
