<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * What a video must know about its neighbours (FR-28, FR-33, A7/D7).
 */
final readonly class ContinuityContext
{
    /**
     * @param  list<array{number: int, title: string, taught: string, from_script: bool}>  $predecessors
     * @param  list<int>  $sourceVideoIds
     */
    public function __construct(
        public int $videoNumber,
        public ?string $blockTitle,
        public ?int $blockNumber,
        public int $positionInBlock,
        public int $videosInBlock,
        public bool $isFirstOfCourse,
        public bool $isFirstOfBlock,
        public array $predecessors,
        public ?int $nextVideoNumber,
        public ?string $nextVideoTitle,
        public bool $isProvisional,
        public array $sourceVideoIds,
    ) {}

    public function hasNextVideo(): bool
    {
        return $this->nextVideoNumber !== null;
    }

    public function positionLabel(string $language): string
    {
        return $language === 'en'
            ? sprintf('%d of %d in the block', $this->positionInBlock, $this->videosInBlock)
            : sprintf('%d de %d del bloque', $this->positionInBlock, $this->videosInBlock);
    }
}
