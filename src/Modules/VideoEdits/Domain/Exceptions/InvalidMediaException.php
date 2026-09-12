<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Exceptions;

use DomainException;

/**
 * The uploaded files are not usable video, or exceed the duration limit (D2).
 */
final class InvalidMediaException extends DomainException implements PermanentVideoEditFailure
{
    public const string FAILURE_CODE = 'invalid_media';

    /**
     * @param  array<string, mixed>  $details
     */
    private function __construct(
        string $message,
        private readonly array $details,
    ) {
        parent::__construct($message);
    }

    public static function unsupportedSource(int $position): self
    {
        return new self(
            "Clip {$position} is not a supported video.",
            ['sources' => [['position' => $position, 'error' => 'unsupported_format']]],
        );
    }

    public static function tooLong(int $totalDurationMs, int $maximumDurationMs): self
    {
        return new self(
            sprintf('The videos last longer than the %d-minute limit.', intdiv($maximumDurationMs, 60_000)),
            ['total_duration_ms' => $totalDurationMs, 'maximum_duration_ms' => $maximumDurationMs],
        );
    }

    /**
     * V2 — the extracted audio is past what the transcription provider accepts.
     * Permanent: a retry re-extracts exactly the same bytes.
     */
    public static function audioTooLargeToTranscribe(int $sizeBytes, int $maximumBytes): self
    {
        return new self(
            sprintf(
                'The recording is too long to transcribe (%d MB of audio, limit %d MB). Split it into shorter videos.',
                intdiv($sizeBytes, 1024 * 1024),
                intdiv($maximumBytes, 1024 * 1024),
            ),
            ['audio_size_bytes' => $sizeBytes, 'maximum_bytes' => $maximumBytes],
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
