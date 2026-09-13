<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Ai;

use Modules\CourseScripts\Domain\ValueObjects\NotesExcerpt;
use Modules\CourseScripts\Domain\ValueObjects\ResearchFinding;
use Modules\CourseScripts\Domain\ValueObjects\VideoWritingContext;
use Shared\Infrastructure\AI\PromptCache\CacheablePrompt;
use Shared\Infrastructure\AI\PromptCache\PromptLayer;

/**
 * Renders a {@see VideoWritingContext} into the cacheable prompt layers.
 *
 * Deterministic by construction — fixed section order, no timestamps, no
 * random ids, JSON with stable key order — so the same context yields the same
 * bytes for every step of a video, and the same course layer for every video.
 */
final readonly class WritingContextRenderer
{
    #[\NoDiscard]
    public function prompt(VideoWritingContext $context, string $tail): CacheablePrompt
    {
        return new CacheablePrompt(
            layers: [
                PromptLayer::long($this->courseLayer($context)),
                PromptLayer::short($this->videoLayer($context)),
            ],
            tail: $tail,
            cacheKey: 'course-scripts:'.$context->courseUuid,
        );
    }

    private function courseLayer(VideoWritingContext $context): string
    {
        $header = "COURSE LANGUAGE: {$context->language}\n"
            .'TAUGHT TOOL: '.($context->taughtTool() ?? 'none — do not write on-screen prompts')."\n"
            .'SOURCE PRECEDENCE: author notes > video brief > research > your own knowledge.';

        return $header."\n\n".UntrustedContentBlock::wrapAll(array_filter([
            'course_title' => $context->courseTitle,
            'course_bible' => $context->bible === null ? null : $this->json($context->bible),
            'subject_research' => $this->research($context->courseResearch),
            'style_exemplar' => $context->styleExemplar,
        ], static fn (?string $value): bool => $value !== null && trim($value) !== ''));
    }

    private function videoLayer(VideoWritingContext $context): string
    {
        $continuity = $context->continuity;
        $lines = [
            "VIDEO {$context->videoNumber}: {$context->videoTitle}",
            'Topic: '.($context->topic ?? '—'),
            "Duration: {$context->durationMinutes} minutes",
            'Block: '.($continuity->blockNumber === null ? 'none' : $continuity->blockNumber.' – '.$continuity->blockTitle),
            'Position: '.$continuity->positionLabel($context->language),
            'First video of the course: '.($continuity->isFirstOfCourse ? 'yes' : 'no'),
            'First video of its block: '.($continuity->isFirstOfBlock ? 'yes' : 'no'),
            'Next video: '.($continuity->hasNextVideo() ? $continuity->nextVideoNumber.' – '.$continuity->nextVideoTitle : 'none (this is the last video)'),
        ];

        foreach ($continuity->predecessors as $previous) {
            $lines[] = sprintf('Previous video %d – %s: %s', $previous['number'], $previous['title'], $previous['taught']);
        }

        $sections = [
            'video_facts' => implode("\n", $lines),
            'video_brief' => $this->json($context->brief),
        ];

        foreach ($context->notes as $index => $excerpt) {
            $sections['author_notes_'.($index + 1)] = $this->excerpt($excerpt);
        }

        $sections['video_research'] = $this->research($context->videoResearch);

        if ($context->feedbackNote !== null && trim($context->feedbackNote) !== '') {
            $sections['author_feedback_on_previous_version'] = $context->feedbackNote;
        }

        if ($context->forcePractice) {
            $sections['author_request'] = 'The author requires a practice pack for this video.';
        }

        return UntrustedContentBlock::wrapAll(array_filter($sections, static fn (?string $value): bool => $value !== null && trim($value) !== ''));
    }

    /**
     * @param  list<ResearchFinding>  $findings
     */
    private function research(array $findings): ?string
    {
        if ($findings === []) {
            return null;
        }

        return implode("\n\n", array_map(
            static fn (ResearchFinding $finding): string => "SOURCE: {$finding->title}\nURL: {$finding->url}\n{$finding->content}",
            $findings,
        ));
    }

    private function excerpt(NotesExcerpt $excerpt): string
    {
        return $excerpt->label."\n".$excerpt->text;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
