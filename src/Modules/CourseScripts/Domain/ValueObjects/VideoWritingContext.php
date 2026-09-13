<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * Everything the writer knows about one video (plan §3.5 steps 1–3), built
 * once and reused verbatim by every step so the prompt prefix stays cacheable.
 *
 * Precedence the writer is told to honour: author notes > brief > research >
 * model knowledge (DEC-6).
 */
final readonly class VideoWritingContext
{
    /**
     * @param  array<string, mixed>|null  $bible
     * @param  array<string, mixed>  $brief  objective, learning_areas, audience_objectives, mandatory_content, errors_to_avoid, expected_result
     * @param  list<NotesExcerpt>  $notes
     * @param  list<ResearchFinding>  $courseResearch
     * @param  list<ResearchFinding>  $videoResearch
     */
    public function __construct(
        public string $courseUuid,
        public string $courseTitle,
        public string $language,
        public ?array $bible,
        public int $videoNumber,
        public string $videoTitle,
        public ?string $topic,
        public int $durationMinutes,
        public array $brief,
        public array $notes,
        public array $courseResearch,
        public array $videoResearch,
        public ContinuityContext $continuity,
        public ?string $styleExemplar = null,
        public bool $forcePractice = false,
        public ?string $feedbackNote = null,
    ) {}

    public function taughtTool(): ?string
    {
        $tool = trim((string) ($this->bible['taught_tool'] ?? ''));

        return $tool === '' ? null : $tool;
    }

    /**
     * @return list<string>
     */
    public function mandatoryContent(): array
    {
        return array_values(array_map(strval(...), (array) ($this->brief['mandatory_content'] ?? [])));
    }

    /**
     * @return list<string>
     */
    public function errorsToAvoid(): array
    {
        return array_values(array_map(strval(...), (array) ($this->brief['errors_to_avoid'] ?? [])));
    }

    public function isGrounded(): bool
    {
        return $this->courseResearch !== [] || $this->videoResearch !== [];
    }

    /**
     * @return list<int>
     */
    public function researchFindingIds(): array
    {
        return array_values(array_filter(array_map(
            static fn (ResearchFinding $finding): ?int => $finding->id,
            [...$this->courseResearch, ...$this->videoResearch],
        )));
    }
}
