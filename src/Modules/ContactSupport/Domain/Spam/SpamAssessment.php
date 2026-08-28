<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Domain\Spam;

/**
 * The verdict {@see SpamGuard} returns for one public contact-form submission.
 *
 * Immutable and I/O-free: `score` is clamped to 0–100 (the `spam_score` column
 * is an unsigned smallint and the admin UI reads it as a percentage), `reasons`
 * is the ordered list of signal codes that fired, and `isSpam` is the result of
 * comparing `score` against the configured threshold.
 */
final readonly class SpamAssessment
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public int $score,
        public array $reasons,
        public bool $isSpam,
    ) {}

    public static function clean(): self
    {
        return new self(0, [], false);
    }

    /**
     * `spam_reasons` is stored as `null` rather than `[]` when nothing fired,
     * matching the column default and the factory.
     *
     * @return list<string>|null
     */
    public function reasonsForStorage(): ?array
    {
        return $this->reasons === [] ? null : $this->reasons;
    }
}
