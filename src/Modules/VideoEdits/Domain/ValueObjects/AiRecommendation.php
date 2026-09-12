<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use Modules\VideoEdits\Domain\Enums\AiRecommendationKind;

/**
 * Advice for the user, never an action (US-14, decision R6/R7).
 *
 * Times are optional because the most useful recommendations often have no
 * boundary at all — "the intro runs 3 minutes against a 1-minute budget" is
 * about the whole section.
 */
final readonly class AiRecommendation
{
    public function __construct(
        public AiRecommendationKind $kind,
        public string $title,
        public string $detail,
        public ?int $startMs = null,
        public ?int $endMs = null,
    ) {}

    /**
     * @return array{kind: string, title: string, detail: string, start_ms: int|null, end_ms: int|null}
     */
    #[\NoDiscard]
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'title' => $this->title,
            'detail' => $this->detail,
            'start_ms' => $this->startMs,
            'end_ms' => $this->endMs,
        ];
    }
}
