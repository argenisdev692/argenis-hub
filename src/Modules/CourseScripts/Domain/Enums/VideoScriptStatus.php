<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Enums;

/**
 * Where a video stands in script generation (US-10 filters).
 */
enum VideoScriptStatus: string
{
    case NotStarted = 'not_started';
    case Generating = 'generating';
    case Generated = 'generated';
    case Failed = 'failed';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::NotStarted => [self::Generating],
            // Back to NotStarted when a run is cancelled before the video started.
            self::Generating => [self::Generated, self::Failed, self::NotStarted],
            self::Generated => [self::Generating],
            self::Failed => [self::Generating],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Generated || $this === self::Failed;
    }
}
