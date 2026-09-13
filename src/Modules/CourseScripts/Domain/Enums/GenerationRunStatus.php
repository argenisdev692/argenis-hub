<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Enums;

/**
 * Lifecycle of a generation run (FR-16…FR-25). `Queued` and `Running` are the
 * active states — at most one per course, enforced by a partial unique index.
 */
enum GenerationRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case PartiallyFailed = 'partially_failed';
    case Cancelled = 'cancelled';
    case StoppedAtCeiling = 'stopped_at_ceiling';
    case Failed = 'failed';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Queued => [self::Running, self::Cancelled, self::Failed],
            self::Running => [self::Completed, self::PartiallyFailed, self::Cancelled, self::StoppedAtCeiling, self::Failed],
            self::Completed, self::PartiallyFailed, self::Cancelled, self::StoppedAtCeiling, self::Failed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }

    public function isTerminal(): bool
    {
        return ! $this->isActive();
    }

    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function activeValues(): array
    {
        return [self::Queued->value, self::Running->value];
    }
}
