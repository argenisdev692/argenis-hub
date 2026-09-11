<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Exceptions;

use DomainException;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Throwable;

/**
 * The edit is not in a state that allows the requested action (HTTP 409).
 */
final class VideoEditStateConflictException extends DomainException
{
    private function __construct(
        string $message,
        public readonly string $reasonCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function alreadyActive(?Throwable $previous = null): self
    {
        return new self('You already have a video edit in progress.', 'already_active', $previous);
    }

    public static function invalidState(VideoEditStatus $current): self
    {
        return new self("This action is not available while the edit is {$current->value}.", 'invalid_state');
    }

    public static function notRetryable(): self
    {
        return new self('This edit can no longer be retried. Start a new edit from its settings instead.', 'not_retryable');
    }

    public static function notCompleted(): self
    {
        return new self('The edited video is not ready yet.', 'not_completed');
    }
}
