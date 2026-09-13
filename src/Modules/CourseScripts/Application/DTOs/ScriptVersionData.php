<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseDeliverableEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseResearchFindingEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseScriptVersionEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A script version as the author previews it: the whole structure, the
 * practice pack, research sources, review results and downloadable files
 * (US-5…US-8, US-13, US-14). Storage paths never leave the backend.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ScriptVersionData extends Data
{
    /**
     * @param  array<string, mixed>  $technicalHeader
     * @param  list<string>  $learningObjectives
     * @param  list<array<string, mixed>>  $sections
     * @param  list<string>  $summaryPoints
     * @param  array<string, mixed>|null  $nextVideo
     * @param  array<string, mixed>  $recordingNotes
     * @param  list<string>  $verificationChecklist
     * @param  array<string, mixed>|null  $errorsCheck
     * @param  array<string, int>|null  $reviewScores
     * @param  list<array{target: string, text: string}>|null  $reviewObjections
     * @param  array<string, mixed>|null  $practice
     * @param  list<array{title: string, url: string, provider: string, full_page_fetched: bool}>  $sources
     * @param  list<array{uuid: string, document_type: string, artifact_file_name: string, format: string, size_bytes: int}>  $deliverables
     */
    public function __construct(
        public string $uuid,
        public int $version,
        public bool $isAccepted,
        public string $writerProvider,
        public string $createdAt,
        public array $technicalHeader,
        public array $learningObjectives,
        public string $continuityNote,
        public bool $continuityIsProvisional,
        public bool $continuityStale,
        public array $sections,
        public bool $usesTool,
        public array $summaryPoints,
        public ?array $nextVideo,
        public array $recordingNotes,
        public array $verificationChecklist,
        public ?array $errorsCheck,
        public bool $isGrounded,
        public ?string $promptsSheetReason,
        public bool $practiceWarranted,
        public ?string $practiceDecisionReason,
        public bool $reviewed,
        public ?string $reviewerProvider,
        public ?array $reviewScores,
        public ?array $reviewObjections,
        public int $reviewIterations,
        public ?bool $passedReview,
        public ?string $feedbackNote,
        public ?array $practice,
        public array $sources,
        public array $deliverables,
    ) {}

    public static function fromModel(CourseScriptVersionEloquentModel $version): self
    {
        $version->loadMissing(['practice', 'sources', 'deliverables']);
        $practice = $version->practice;

        return new self(
            uuid: $version->uuid,
            version: $version->version,
            isAccepted: $version->is_accepted,
            writerProvider: $version->writer_provider,
            createdAt: $version->created_at?->toIso8601String() ?? '',
            technicalHeader: $version->technical_header,
            learningObjectives: $version->learning_objectives,
            continuityNote: $version->continuity_note,
            continuityIsProvisional: $version->continuity_is_provisional,
            continuityStale: $version->continuity_stale,
            sections: $version->sections,
            usesTool: $version->uses_tool,
            summaryPoints: $version->summary_points,
            nextVideo: $version->next_video,
            recordingNotes: $version->recording_notes,
            verificationChecklist: $version->verification_checklist,
            errorsCheck: $version->errors_check,
            isGrounded: $version->is_grounded,
            promptsSheetReason: $version->prompts_sheet_reason,
            practiceWarranted: $version->practice_warranted,
            practiceDecisionReason: $version->practice_decision_reason,
            reviewed: $version->reviewed,
            reviewerProvider: $version->reviewer_provider,
            reviewScores: $version->review_scores,
            reviewObjections: $version->review_objections,
            reviewIterations: $version->review_iterations,
            passedReview: $version->passed_review,
            feedbackNote: $version->feedback_note,
            practice: $practice === null ? null : [
                'uuid' => $practice->uuid,
                'decided_by' => $practice->decided_by->value,
                'decision_reason' => $practice->decision_reason,
                'document_name' => $practice->document_name,
                'header_title' => $practice->header_title,
                'files_summary' => $practice->files_summary,
                'setup_instruction' => $practice->setup_instruction,
                'usage' => $practice->usage,
                'instructor_note' => $practice->instructor_note,
                'designed_contrasts' => $practice->designed_contrasts,
                'artifacts' => $practice->artifacts,
                'review_scores' => $practice->review_scores,
                'review_objections' => $practice->review_objections,
            ],
            sources: $version->sources->map(static fn (CourseResearchFindingEloquentModel $source): array => [
                'title' => $source->title,
                'url' => $source->url,
                'provider' => $source->provider,
                'full_page_fetched' => $source->full_page_fetched,
            ])->values()->all(),
            deliverables: $version->deliverables->map(static fn (CourseDeliverableEloquentModel $deliverable): array => [
                'uuid' => $deliverable->uuid,
                'document_type' => $deliverable->document_type->value,
                'artifact_file_name' => $deliverable->artifact_file_name,
                'format' => $deliverable->format->value,
                'size_bytes' => $deliverable->size_bytes,
            ])->values()->all(),
        );
    }
}
