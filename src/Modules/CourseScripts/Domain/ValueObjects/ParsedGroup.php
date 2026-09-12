<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * An optional grouping of points — a block, module, section, unit, part
 * (spec FR-2a).
 *
 * Optional is the operative word. Indexes that organise their points get real
 * groups; a bare table of contents gets one implicit group covering everything.
 * Either way a point always belongs to exactly one, so downstream code never
 * branches on whether the author happened to use blocks.
 *
 * Groups matter beyond tidiness: a point's position within its group appears in
 * the script's technical header ("Vídeo 2 de 5 del bloque") and shapes the
 * opening of a group's first point, both of which the reference scripts carry.
 */
final readonly class ParsedGroup
{
    public function __construct(
        public int $number,
        public string $title,
        public ?int $declaredDurationMinutes,
        public int $position,
        /**
         * True when the parser synthesised this group because the index had no
         * grouping of its own. Recorded rather than hidden so the UI can avoid
         * showing an author a "Section 1" they never wrote.
         */
        public bool $isImplicit = false,
    ) {}

    public static function implicit(string $title = 'All points'): self
    {
        return new self(
            number: 1,
            title: $title,
            declaredDurationMinutes: null,
            position: 0,
            isImplicit: true,
        );
    }
}
