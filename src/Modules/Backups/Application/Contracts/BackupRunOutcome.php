<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Contracts;

/**
 * Result of one invocation of the database-backup runner: the process exit code
 * and the console output it produced (used for the audit tail and, on failure,
 * the stored error message).
 */
final readonly class BackupRunOutcome
{
    public function __construct(
        public int $exitCode,
        public string $output,
    ) {}

    #[\NoDiscard]
    public function successful(): bool
    {
        return $this->exitCode === 0;
    }
}
