<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Entities\JobPosting;
use Modules\LeadScout\Domain\ValueObjects\NewJobPosting;

interface JobPostingRepositoryPort
{
    public function byUuid(string $uuid): ?JobPosting;

    public function idByFingerprint(string $fingerprint): ?int;

    /**
     * @return list<JobPosting> newest first
     */
    /**
     * @return list<JobPosting> every status, oldest first
     */
    public function forCompany(int $companyId): array;

    public function activeForCompany(int $companyId): array;

    public function create(NewJobPosting $posting): JobPosting;

    /**
     * Links the posting to one more source; linking twice is a no-op.
     */
    public function attachSource(int $postingId, int $sourceId): void;

    public function assignCompany(int $postingId, int $companyId): void;

    /**
     * Expires active postings published (or, undated, created) before `$cutoff`.
     *
     * @return int postings expired
     */
    public function expireActiveOlderThan(DateTimeImmutable $cutoff): int;
}
