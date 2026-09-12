<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Queries;

use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Ports\AiReportStorePort;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Modules\VideoEdits\Domain\ValueObjects\AiRecommendation;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditCutDecisionEloquentModel;

/**
 * The AI decision report (US-14).
 *
 * Built entirely from stored data — decisions, recommendations, totals — so it
 * never reprocesses the video (EX-8). That is also why the report survives the
 * source files being deleted the moment the edit completed.
 */
final readonly class GetVideoEditReportHandler
{
    public function __construct(
        private VideoEditRepositoryPort $edits,
        private AiReportStorePort $reports,
    ) {}

    /**
     * @return array{edit: array<string, mixed>, decisions: list<array<string, mixed>>, recommendations: list<array<string, mixed>>, conclusion: string|null}
     *
     * @throws VideoEditNotFoundException
     */
    #[\NoDiscard]
    public function handle(string $uuid, int $userId): array
    {
        $edit = $this->edits->findOwnedWithDetails($uuid, $userId)
            ?? throw VideoEditNotFoundException::forUuid($uuid);

        $analysis = $this->reports->forEdit($edit->id);

        // Only the AI's own decisions belong in an AI report; silence and
        // filler cuts are the edit summary's business, not this document's.
        $decisions = $edit->cutDecisions
            ->filter(static fn (VideoEditCutDecisionEloquentModel $decision): bool => $decision->origin === DecisionOrigin::Ai)
            ->map(static fn (VideoEditCutDecisionEloquentModel $decision): array => [
                'reason' => $decision->reason->value,
                'start_ms' => $decision->start_ms,
                'end_ms' => $decision->end_ms,
                'duration_ms' => $decision->end_ms - $decision->start_ms,
                'confidence' => $decision->confidence,
                'outcome' => $decision->outcome->value,
                'rejection_reason' => $decision->rejection_reason?->value,
                'evidence' => is_array($decision->evidence) ? ($decision->evidence['text'] ?? null) : null,
            ])
            ->values()
            ->all();

        return [
            'edit' => [
                'uuid' => $edit->uuid,
                'mode' => $edit->mode->value,
                'status' => $edit->status->value,
                'original_duration_ms' => $edit->original_duration_ms,
                'final_duration_ms' => $edit->final_duration_ms,
                'removed_duration_ms' => $edit->removed_duration_ms,
                'applied_cut_count' => $edit->applied_cut_count,
                'rejected_decision_count' => $edit->rejected_decision_count,
                'script_name' => $edit->script?->original_name,
                'completed_at' => $edit->completed_at?->toIso8601String(),
            ],
            'decisions' => $decisions,
            'recommendations' => array_map(
                static fn (AiRecommendation $recommendation): array => $recommendation->toArray(),
                $analysis?->recommendations ?? [],
            ),
            'conclusion' => $analysis?->conclusion,
        ];
    }
}
