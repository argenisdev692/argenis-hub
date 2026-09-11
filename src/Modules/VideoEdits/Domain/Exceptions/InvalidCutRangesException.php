<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Exceptions;

use DomainException;

/**
 * The requested cuts cannot be applied to the real media (P1 / AD-16).
 *
 * Carries a user-safe failure code and per-range details only — it is persisted
 * as `failure_code` / `failure_details` and lets the owner correct the ranges on
 * retry.
 */
final class InvalidCutRangesException extends DomainException implements PermanentVideoEditFailure
{
    public const string FAILURE_CODE = 'invalid_cut_ranges';

    /**
     * @param  array<string, mixed>  $details
     */
    private function __construct(
        string $message,
        public readonly array $details,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  list<array{index: int, error: string}>  $rangeErrors
     */
    public static function forManualRanges(array $rangeErrors): self
    {
        return new self('Some manual ranges fall outside the video.', ['manual_ranges' => $rangeErrors]);
    }

    public static function outputTooShort(int $finalDurationMs, int $minimumOutputMs): self
    {
        return new self(
            'The requested cuts would leave almost nothing of the video.',
            ['output' => ['final_duration_ms' => $finalDurationMs, 'minimum_duration_ms' => $minimumOutputMs]],
        );
    }

    public function failureCode(): string
    {
        return self::FAILURE_CODE;
    }

    public function failureDetails(): array
    {
        return $this->details;
    }
}
