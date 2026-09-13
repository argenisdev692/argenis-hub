<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Generation;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\CourseScripts\Domain\Enums\SourceDocumentKind;
use Modules\CourseScripts\Domain\Ports\ResearchFindingRepositoryPort;
use Modules\CourseScripts\Domain\Ports\ResearchPort;
use Modules\CourseScripts\Domain\Ports\ScriptVersionRepositoryPort;
use Modules\CourseScripts\Domain\Services\ContinuityContextBuilder;
use Modules\CourseScripts\Domain\Services\NotesExcerptSelector;
use Modules\CourseScripts\Domain\Services\ResearchQueryFactory;
use Modules\CourseScripts\Domain\ValueObjects\ResearchFinding;
use Modules\CourseScripts\Domain\ValueObjects\VideoWritingContext;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseBlockEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseSourceDocumentEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoEloquentModel;

/**
 * Plan §3.5 steps 1–3: continuity, the author's notes, and video research —
 * gathered once per video into the context every writing step shares.
 */
final readonly class VideoWritingContextFactory
{
    private const int MAX_COURSE_FINDINGS = 6;

    public function __construct(
        private ContinuityContextBuilder $continuity,
        private NotesExcerptSelector $notes,
        private ResearchQueryFactory $queries,
        private ResearchPort $research,
        private ResearchFindingRepositoryPort $findings,
        private ScriptVersionRepositoryPort $versions,
        private Config $config,
    ) {}

    /**
     * @return array{context: VideoWritingContext, research_calls: int}
     */
    public function build(CourseEloquentModel $course, CourseVideoEloquentModel $video, bool $forcePractice = false, ?string $feedbackNote = null): array
    {
        $course->loadMissing([
            'blocks' => static fn ($query) => $query->select(['id', 'course_id', 'number', 'title'])->orderBy('number'),
            'videos' => static fn ($query) => $query->select(['id', 'uuid', 'course_id', 'course_block_id', 'number', 'title', 'objective'])->orderBy('number'),
            'sourceDocuments' => static fn ($query) => $query->select(['id', 'uuid', 'course_id', 'course_video_id', 'kind', 'original_name', 'extracted_text']),
        ]);

        $summaries = $this->versions->acceptedSummaries($course->id);

        $continuity = $this->continuity->build(
            $video->id,
            $course->videos->map(static fn (CourseVideoEloquentModel $candidate): array => [
                'id' => $candidate->id,
                'number' => $candidate->number,
                'title' => $candidate->title,
                'block_id' => $candidate->course_block_id,
                'objective' => $candidate->objective,
                'taught_summary' => $summaries[$candidate->id] ?? null,
            ])->values()->all(),
            $course->blocks->mapWithKeys(static fn (CourseBlockEloquentModel $block): array => [$block->id => ['number' => $block->number, 'title' => $block->title]])->all(),
        );

        $documents = static fn (bool $assigned): array => $course->sourceDocuments
            ->filter(static fn (CourseSourceDocumentEloquentModel $document): bool => $document->kind === SourceDocumentKind::Content
                && ($assigned ? $document->course_video_id === $video->id : $document->course_video_id === null))
            ->map(static fn (CourseSourceDocumentEloquentModel $document): array => [
                'id' => $document->uuid,
                'name' => $document->original_name,
                'text' => (string) $document->extracted_text,
            ])
            ->values()
            ->all();

        $brief = [
            'objective' => $video->objective,
            'learning_areas' => $video->learning_areas,
            'audience_objectives' => $video->audience_objectives,
            'mandatory_content' => $video->mandatory_content,
            'errors_to_avoid' => $video->errors_to_avoid,
            'expected_result' => $video->expected_result,
        ];

        $excerpts = $this->notes->select(
            focusText: implode(' ', array_filter([$video->title, $video->topic, $video->objective, ...$video->mandatory_content])),
            videoNotes: $video->notes,
            assignedDocuments: $documents(true),
            courseNotes: $course->course_notes,
            unassignedDocuments: $documents(false),
        );

        ['findings' => $videoFindings, 'calls' => $calls] = $this->researchVideo($course, $video);

        $styleExemplar = $course->sourceDocuments
            ->first(static fn (CourseSourceDocumentEloquentModel $document): bool => $document->kind === SourceDocumentKind::StyleReference)
            ?->extracted_text;

        return [
            'context' => new VideoWritingContext(
                courseUuid: $course->uuid,
                courseTitle: $course->title,
                language: $course->language,
                bible: $course->bible,
                videoNumber: $video->number,
                videoTitle: $video->title,
                topic: $video->topic,
                durationMinutes: $video->declared_duration_minutes ?? $course->default_video_minutes,
                brief: $brief,
                notes: $excerpts,
                courseResearch: array_slice($this->findings->forCourse($course->id), 0, self::MAX_COURSE_FINDINGS),
                videoResearch: $videoFindings,
                continuity: $continuity,
                styleExemplar: $styleExemplar === null ? null : mb_substr($styleExemplar, 0, (int) $this->config->get('course-scripts.style.exemplar_budget_chars', 6000)),
                forcePractice: $forcePractice,
                feedbackNote: $feedbackNote,
            ),
            'research_calls' => $calls,
        ];
    }

    /**
     * Reuses earlier findings for the video; otherwise searches, escalates thin
     * snippets to a full page (capped), and stores the result (FR-13b/c).
     *
     * @return array{findings: list<ResearchFinding>, calls: int}
     */
    private function researchVideo(CourseEloquentModel $course, CourseVideoEloquentModel $video): array
    {
        $existing = $this->findings->forVideo($video->id);

        if ($existing !== []) {
            return ['findings' => $existing, 'calls' => 0];
        }

        $queries = $this->queries->videoQueries(
            $course->title,
            $video->title,
            $video->topic,
            $video->objective,
            mb_strlen((string) $video->notes) >= (int) $this->config->get('course-scripts.notes.rich_notes_threshold_chars', 1500),
            (int) $this->config->get('course-scripts.research.point_queries_max', 2),
        );

        $batch = $this->research->search($queries, (string) $this->config->get('course-scripts.research.recency', 'year'));
        $calls = $batch->calls;
        $findings = $batch->findings;

        usort($findings, static fn (ResearchFinding $a, ResearchFinding $b): int => ($b->score ?? 0) <=> ($a->score ?? 0));

        $thin = (int) $this->config->get('course-scripts.research.thin_snippet_chars', 300);
        $fetches = (int) $this->config->get('course-scripts.research.max_firecrawl_per_video', 2);

        foreach ($findings as $index => $finding) {
            if ($fetches <= 0) {
                break;
            }

            if (mb_strlen($finding->content) >= $thin) {
                continue;
            }

            $fetches--;
            $calls++;
            $full = $this->research->fetchFullPage($finding);

            if ($full !== null) {
                $findings[$index] = $full;
            }
        }

        return ['findings' => $this->findings->save($course->id, $video->id, $findings), 'calls' => $calls];
    }
}
