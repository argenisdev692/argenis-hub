<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\CourseScripts\Domain\Enums\BibleOrigin;
use Modules\CourseScripts\Domain\Enums\SourceDocumentKind;
use Modules\CourseScripts\Domain\Exceptions\GenerationProviderException;
use Modules\CourseScripts\Domain\Ports\BibleProposerPort;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Domain\Ports\ResearchFindingRepositoryPort;
use Modules\CourseScripts\Domain\Ports\ResearchPort;
use Modules\CourseScripts\Domain\Services\ResearchQueryFactory;
use Modules\CourseScripts\Domain\ValueObjects\BibleProposalContext;
use Modules\CourseScripts\Domain\ValueObjects\CallUsage;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseBlockEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoEloquentModel;

/**
 * Once per course, before the first video is written (US-3, US-4 · plan §3.3):
 * propose the bible when the author has none, and research the subject once so
 * every video reuses it (FR-13j).
 *
 * Idempotent: an author-set bible is never overwritten, and subject research
 * never runs twice.
 */
final readonly class PrepareCourseHandler
{
    private const int SAMPLE_BRIEFS = 5;

    public function __construct(
        private CourseRepositoryPort $courses,
        private BibleProposerPort $bibleProposer,
        private ResearchPort $research,
        private ResearchFindingRepositoryPort $findings,
        private ResearchQueryFactory $queries,
        private Config $config,
    ) {}

    /**
     * @throws GenerationProviderException when the bible cannot be proposed
     */
    public function handle(CourseEloquentModel $course, string $provider): CallUsage
    {
        $course->loadMissing([
            'blocks' => static fn ($query) => $query->select(['id', 'course_id', 'number', 'title'])->orderBy('number'),
            'videos' => static fn ($query) => $query->orderBy('number'),
            'sourceDocuments' => static fn ($query) => $query->select(['id', 'course_id', 'kind', 'extracted_text']),
        ]);

        $usage = CallUsage::none();

        if ($course->bible === null) {
            $bible = $this->bibleProposer->propose($this->context($course), $provider);
            $this->courses->saveBible($course, $bible->toArray(), BibleOrigin::Proposed);
            $usage = $usage->add(new CallUsage(aiWrite: 1));
        }

        if (! $this->findings->hasCourseFindings($course->id)) {
            $batch = $this->research->search(
                $this->queries->subjectQueries(
                    $course->title,
                    $course->blocks->map(static fn (CourseBlockEloquentModel $block): string => $block->title)->values()->all(),
                    $course->language,
                    (int) $this->config->get('course-scripts.research.subject_queries', 4),
                ),
                (string) $this->config->get('course-scripts.research.recency', 'year'),
            );

            $this->findings->save($course->id, null, $batch->findings);
            $usage = $usage->add(new CallUsage(research: $batch->calls));
        }

        if ($course->prepared_at === null) {
            $this->courses->markPrepared($course);
        }

        return $usage;
    }

    private function context(CourseEloquentModel $course): BibleProposalContext
    {
        $toc = $course->videos
            ->map(static fn (CourseVideoEloquentModel $video): string => $video->number.'. '.$video->title)
            ->values()
            ->all();

        $briefs = $course->videos
            ->filter(static fn (CourseVideoEloquentModel $video): bool => $video->objective !== null || $video->notes !== null)
            ->take(self::SAMPLE_BRIEFS)
            ->map(static fn (CourseVideoEloquentModel $video): string => trim($video->number.'. '.$video->title."\n".($video->objective ?? '')."\n".mb_substr((string) $video->notes, 0, 1500)))
            ->implode("\n\n");

        $contentNotes = $course->sourceDocuments
            ->filter(static fn ($document): bool => $document->kind === SourceDocumentKind::Content)
            ->map(static fn ($document): string => mb_substr((string) $document->extracted_text, 0, 3000))
            ->implode("\n\n");

        $styleExemplar = $course->sourceDocuments
            ->first(static fn ($document): bool => $document->kind === SourceDocumentKind::StyleReference)
            ?->extracted_text;

        return new BibleProposalContext(
            courseTitle: $course->title,
            language: $course->language,
            tableOfContents: $toc,
            courseNotes: trim(((string) $course->course_notes)."\n\n".$contentNotes) ?: null,
            sampleBriefs: $briefs === '' ? null : $briefs,
            styleExemplar: $styleExemplar === null ? null : mb_substr($styleExemplar, 0, (int) $this->config->get('course-scripts.style.exemplar_budget_chars', 6000)),
        );
    }
}
