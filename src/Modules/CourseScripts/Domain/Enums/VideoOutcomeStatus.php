<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Enums;

enum VideoOutcomeStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Skipped], true);
    }
}
