<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Entities\Source;
use Modules\LeadScout\Domain\Enums\FetchStatus;
use Modules\LeadScout\Domain\Enums\SourceStatus;

interface SourceRepositoryPort
{
    /**
     * @return list<Source> highest priority first, then by name
     */
    public function all(): array;

    public function byUuid(string $uuid): ?Source;

    public function configure(Source $source, SourceStatus $status, int $frequencyMinutes, ?DateTimeImmutable $termsReviewedAt): Source;

    public function saveCursor(Source $source, string $cursor): void;

    public function markRun(Source $source, DateTimeImmutable $at): void;

    public function setStatus(Source $source, SourceStatus $status): void;

    /**
     * Appends one ingest attempt (spec US-8 metrics).
     */
    public function recordAttempt(Source $source, FetchStatus $status, int $durationMs, ?string $error): void;
}
