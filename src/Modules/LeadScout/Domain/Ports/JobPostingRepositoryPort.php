<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutJobPostingEloquentModel;

/**
 * Posting persistence (mandatory port in the intermediate baseline).
 */
interface JobPostingRepositoryPort
{
    public function findByFingerprint(string $fingerprint): ?ScoutJobPostingEloquentModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ScoutJobPostingEloquentModel;

    public function attachSource(ScoutJobPostingEloquentModel $posting, int $sourceId): void;

    public function markExpired(int $postingId): void;
}
