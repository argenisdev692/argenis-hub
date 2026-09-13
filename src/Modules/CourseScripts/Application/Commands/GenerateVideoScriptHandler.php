<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Modules\CourseScripts\Application\Generation\DraftReviewer;
use Modules\CourseScripts\Application\Generation\GeneratedScript;
use Modules\CourseScripts\Application\Generation\GenerateVideoScriptCommand;
use Modules\CourseScripts\Application\Generation\ScriptDraftWriter;
use Modules\CourseScripts\Application\Generation\VideoWritingContextFactory;
use Modules\CourseScripts\Domain\Enums\BibleOrigin;
use Modules\CourseScripts\Domain\Enums\PracticeDecisionOrigin;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Exceptions\GenerationProviderException;
use Modules\CourseScripts\Domain\Exceptions\ScriptValidationException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Domain\Ports\ScriptVersionRepositoryPort;
use Modules\CourseScripts\Domain\Services\BibleRegistry;
use Modules\CourseScripts\Domain\Services\DocumentNameFactory;
use Modules\CourseScripts\Domain\ValueObjects\CallUsage;
use Modules\CourseScripts\Domain\ValueObjects\NotesExcerpt;
use Modules\CourseScripts\Domain\ValueObjects\ScriptDraft;
use Modules\CourseScripts\Domain\ValueObjects\VideoWritingContext;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseScriptVersionEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoEloquentModel;

/**
 * Writes one video's script, practice pack and closing parts, validates them,
 * optionally sends them through the independent second review, and stores the
 * version (plan §3.5 · US-5, US-7, US-13).
 *
 * Throws on failure so the caller (the queue job) can record a sanitized
 * reason; nothing partial is stored.
 */
final readonly class GenerateVideoScriptHandler
{
    public function __construct(
        private CourseRepositoryPort $courses,
        private ScriptVersionRepositoryPort $versions,
        private VideoWritingContextFactory $contexts,
        private ScriptDraftWriter $writer,
        private DraftReviewer $reviewer,
        private BibleRegistry $registry,
        private DocumentNameFactory $names,
    ) {}

    /**
     * @throws CourseNotFoundException
     * @throws GenerationProviderException
     * @throws ScriptValidationException
     */
    public function handle(GenerateVideoScriptCommand $command, ?CallUsage &$usage = null): GeneratedScript
    {
        $usage = CallUsage::none();
        $course = $this->courses->findById($command->courseId) ?? throw new CourseNotFoundException;
        $video = $course->videos()->whereKey($command->videoId)->first() ?? throw new CourseNotFoundException;

        ['context' => $context, 'research_calls' => $researchCalls] = $this->contexts->build($course, $video, $command->forcePractice, $command->feedbackNote);
        $usage = $usage->add(new CallUsage(research: $researchCalls));

        try {
            $draft = $this->writer->write($context, $command->writerProvider);
        } finally {
            $usage = $usage->add(new CallUsage(aiWrite: $this->writer->callsMade()));
        }

        $review = null;

        if ($command->withReview) {
            $review = $this->reviewer->review($context, $draft, $command->writerProvider, (string) ($command->reviewerProvider ?? $command->writerProvider));
            $draft = $review->draft;
            $usage = $usage->add($review->usage);
        }

        $version = $this->persist($course, $video, $context, $draft, $command, $review?->toAttributes() ?? []);

        return new GeneratedScript($version->id, $usage, $review->iterations ?? 0);
    }

    /**
     * @param  array<string, mixed>  $reviewAttributes
     */
    private function persist(
        CourseEloquentModel $course,
        CourseVideoEloquentModel $video,
        VideoWritingContext $context,
        ScriptDraft $draft,
        GenerateVideoScriptCommand $command,
        array $reviewAttributes,
    ): CourseScriptVersionEloquentModel {
        $closing = (array) $draft->closing;
        $prompts = array_filter($draft->segments(), static fn (array $segment): bool => $segment['type'] === 'on_screen_prompt');
        $continuity = $context->continuity;

        $bibleRevision = $this->mergeBible($course, $draft);

        $version = $this->versions->record(
            attributes: [
                'course_video_id' => $video->id,
                'course_generation_run_id' => $command->runId,
                'writer_provider' => $command->writerProvider,
                'brief_revision' => $video->brief_revision,
                'bible_revision' => $bibleRevision,
                'technical_header' => [
                    'duration_minutes' => $context->durationMinutes,
                    'block_number' => $continuity->blockNumber,
                    'block_title' => $continuity->blockTitle,
                    'recording_format' => $draft->recordingFormat,
                    'position_in_block' => $continuity->positionInBlock,
                    'videos_in_block' => $continuity->videosInBlock,
                    'position_label' => $continuity->positionLabel($context->language),
                ],
                'learning_objectives' => $draft->learningObjectives,
                'continuity_note' => $draft->continuityNote,
                'continuity_is_provisional' => $continuity->isProvisional,
                'continuity_source_video_ids' => $continuity->sourceVideoIds,
                'sections' => $draft->sections,
                'uses_tool' => $draft->usesTool,
                'taught_summary' => $draft->taughtSummary,
                'summary_points' => (array) ($closing['summary_points'] ?? []),
                'next_video' => $continuity->hasNextVideo() ? [
                    'number' => $continuity->nextVideoNumber,
                    'title' => $continuity->nextVideoTitle,
                    'handoff' => (string) ($closing['next_video_handoff'] ?? ''),
                ] : null,
                'recording_notes' => (array) ($closing['recording_notes'] ?? []),
                'verification_checklist' => (array) ($closing['verification_checklist'] ?? []),
                'coverage_map' => $draft->coverageMap,
                'errors_check' => $this->errorsCheck($context, $draft),
                'is_grounded' => $context->isGrounded(),
                'notes_excerpt_ids' => array_values(array_unique(array_map(static fn (NotesExcerpt $excerpt): string => $excerpt->sourceId, $context->notes))),
                'prompts_sheet_reason' => $prompts === [] ? ($draft->usesTool ? 'no_on_screen_prompts' : 'no_taught_tool') : null,
                'practice_warranted' => $draft->practiceWarranted(),
                'practice_decision_reason' => (string) ($draft->practicePlan['reason'] ?? ''),
                'reviewed' => $command->withReview,
                'reviewer_provider' => $command->withReview ? $command->reviewerProvider : null,
                'feedback_note' => $command->feedbackNote,
                ...array_diff_key($reviewAttributes, ['practice_review_scores' => true, 'practice_review_objections' => true]),
            ],
            practice: $draft->practiceWarranted() ? $this->practiceAttributes($context, $draft, $command, $reviewAttributes) : null,
            findingIds: $context->researchFindingIds(),
            accept: $command->accept,
        );

        if ($command->accept) {
            $this->versions->markContinuityStale($course->id, $video->id);
        }

        return $version;
    }

    /**
     * @param  array<string, mixed>  $reviewAttributes
     * @return array<string, mixed>
     */
    private function practiceAttributes(VideoWritingContext $context, ScriptDraft $draft, GenerateVideoScriptCommand $command, array $reviewAttributes): array
    {
        $plan = $draft->practicePlan;
        $usage = [];

        foreach ($draft->sections as $section) {
            foreach ((array) $section['practice_files'] as $file) {
                $usage[] = [
                    'demo_label' => (string) ($section['demo_label'] ?? ''),
                    'section_number' => (string) $section['number'],
                    'purpose' => (string) ($section['demo_purpose'] ?? $section['purpose']),
                    'file_name' => (string) $file,
                ];
            }
        }

        $label = $context->language === 'en' ? 'PRACTICE DOCUMENT · VIDEO' : 'DOCUMENTO DE PRÁCTICA · VÍDEO';

        return [
            'decided_by' => $command->forcePractice ? PracticeDecisionOrigin::AuthorForced : PracticeDecisionOrigin::System,
            'decision_reason' => (string) $plan['reason'],
            'document_name' => $this->names->practiceDocument((string) ($plan['topic'] ?: ($context->topic ?? $context->videoTitle)), $context->videoNumber),
            'header_title' => sprintf('%s %02d — %s', $label, $context->videoNumber, $context->videoTitle),
            'files_summary' => (string) $plan['files_summary'],
            'setup_instruction' => (string) $plan['setup_instruction'],
            'usage' => $usage,
            'instructor_note' => (string) $plan['instructor_note'],
            'designed_contrasts' => (array) $plan['contrasts'],
            'artifacts' => array_values(array_map(static fn (array $artifact): array => [
                ...$artifact,
                'content_blocks' => (array) ($draft->artifacts[$artifact['file_name']]['content_blocks'] ?? []),
                'characters' => (array) ($draft->artifacts[$artifact['file_name']]['characters'] ?? []),
                'organisations_detail' => (array) ($draft->artifacts[$artifact['file_name']]['organisations'] ?? []),
            ], (array) $plan['artifacts'])),
            'review_scores' => $reviewAttributes['practice_review_scores'] ?? null,
            'review_objections' => $reviewAttributes['practice_review_objections'] ?? null,
        ];
    }

    /**
     * New organisations and characters a practice pack introduced join the
     * course bible (FR-13k). Returns the bible revision the script was written against.
     */
    private function mergeBible(CourseEloquentModel $course, ScriptDraft $draft): int
    {
        $organisations = [];
        $characters = [];

        foreach ($draft->artifacts as $artifact) {
            $organisations = [...$organisations, ...(array) ($artifact['organisations'] ?? [])];
            $characters = [...$characters, ...(array) ($artifact['characters'] ?? [])];
        }

        if ($organisations === [] && $characters === []) {
            return $course->bible_revision;
        }

        $merged = $this->registry->merge($course->bible ?? [], $organisations, $characters);

        if ($merged['changed']) {
            $this->courses->saveBible($course, $merged['bible'], $course->bible_origin ?? BibleOrigin::Proposed);
        }

        return $course->bible_revision;
    }

    /**
     * FR-32: which errors to avoid the script visibly addresses. A keyword pass,
     * recorded for the author; the second review judges it properly.
     *
     * @return array{method: string, items: list<array{error: string, addressed: bool}>}
     */
    private function errorsCheck(VideoWritingContext $context, ScriptDraft $draft): array
    {
        $text = mb_strtolower($draft->plainText());

        return [
            'method' => 'keyword_overlap',
            'items' => array_map(static function (string $error) use ($text): array {
                $words = array_filter(preg_split('/\W+/u', mb_strtolower($error)) ?: [], static fn (string $word): bool => mb_strlen($word) >= 5);
                $hits = array_filter($words, static fn (string $word): bool => str_contains($text, $word));

                return ['error' => $error, 'addressed' => $words !== [] && count($hits) >= max(1, (int) floor(count($words) / 2))];
            }, $context->errorsToAvoid()),
        ];
    }
}
