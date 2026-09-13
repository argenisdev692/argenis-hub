<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Services;

use Modules\CourseScripts\Domain\ValueObjects\ContinuityContext;

/**
 * Builds a video's continuity from its neighbours (FR-28, FR-33, FR-33a · D7).
 *
 * A bounded window of predecessors, each summarised by what its accepted
 * script says it taught — or, when no script exists yet, by its brief, which
 * makes the continuity provisional. Video 46 names 43, 44 and 45 in two
 * sentences; it never needs the full text of 45 scripts.
 */
final readonly class ContinuityContextBuilder
{
    public function __construct(
        private int $window = 3,
        private int $maxSummaryChars = 400,
    ) {}

    /**
     * @param  list<array{id: int, number: int, title: string, block_id: ?int, objective: ?string, taught_summary: ?string}>  $videos  whole course, any order
     * @param  array<int, array{number: int, title: string}>  $blocksById
     */
    #[\NoDiscard]
    public function build(int $videoId, array $videos, array $blocksById): ContinuityContext
    {
        usort($videos, static fn (array $a, array $b): int => $a['number'] <=> $b['number']);

        $index = array_search($videoId, array_column($videos, 'id'), true);

        if ($index === false) {
            throw new \InvalidArgumentException('The video is not part of the course.');
        }

        $video = $videos[$index];
        $blockId = $video['block_id'];
        $inBlock = array_values(array_filter($videos, static fn (array $candidate): bool => $candidate['block_id'] === $blockId));
        $position = (int) array_search($videoId, array_column($inBlock, 'id'), true) + 1;

        $predecessors = [];
        $provisional = false;
        $sourceIds = [];

        foreach (array_slice($videos, max(0, $index - $this->window), min($index, $this->window)) as $previous) {
            $fromScript = $previous['taught_summary'] !== null && trim($previous['taught_summary']) !== '';
            $provisional = $provisional || ! $fromScript;
            $sourceIds[] = $previous['id'];

            $predecessors[] = [
                'number' => $previous['number'],
                'title' => $previous['title'],
                'taught' => mb_substr(trim((string) ($fromScript ? $previous['taught_summary'] : ($previous['objective'] ?? $previous['title']))), 0, $this->maxSummaryChars),
                'from_script' => $fromScript,
            ];
        }

        $next = $videos[$index + 1] ?? null;
        $block = $blockId !== null ? ($blocksById[$blockId] ?? null) : null;

        return new ContinuityContext(
            videoNumber: $video['number'],
            blockTitle: $block['title'] ?? null,
            blockNumber: $block['number'] ?? null,
            positionInBlock: $position,
            videosInBlock: count($inBlock),
            isFirstOfCourse: $index === 0,
            isFirstOfBlock: $position === 1,
            predecessors: $predecessors,
            nextVideoNumber: $next['number'] ?? null,
            nextVideoTitle: $next['title'] ?? null,
            isProvisional: $provisional,
            sourceVideoIds: $sourceIds,
        );
    }
}
