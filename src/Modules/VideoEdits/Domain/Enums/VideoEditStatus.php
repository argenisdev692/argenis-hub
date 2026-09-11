<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Enums;

/**
 * Lifecycle of a video edit (spec 001-video-edit, plan AD-9 / AD-11).
 *
 * `Draft` is internal: sources are still uploading, it never appears in the
 * history (D17). `Queued` + `Processing` are the "active" states — a user may
 * hold at most one active edit (FR-16).
 */
enum VideoEditStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Queued],
            self::Queued => [self::Processing, self::Failed],
            self::Processing => [self::Completed, self::Failed],
            self::Failed => [self::Queued],
            self::Completed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Processing;
    }

    /**
     * A running render cannot be deleted from under the worker (D14).
     */
    public function isDeletable(): bool
    {
        return $this !== self::Processing;
    }

    public function isListed(): bool
    {
        return $this !== self::Draft;
    }

    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function activeValues(): array
    {
        return array_values(array_map(
            static fn (self $status): string => $status->value,
            array_filter(self::cases(), static fn (self $status): bool => $status->isActive()),
        ));
    }
}
