<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Exceptions;

use Throwable;

/**
 * A failure that retrying cannot fix (bad media, invalid ranges, no disk).
 * The job fails immediately instead of burning its retries (FR-17), and the
 * code, user-safe message and details are persisted as-is (FR-20).
 */
interface PermanentVideoEditFailure extends Throwable
{
    public function failureCode(): string;

    /**
     * @return array<string, mixed>|null
     */
    public function failureDetails(): ?array;
}
