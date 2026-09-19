<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Enums\PrivacyRequestOutcome;
use Modules\LeadScout\Domain\Enums\PrivacyRequestType;
use Modules\LeadScout\Domain\Ports\PrivacyRequestRepositoryPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutPrivacyRequestEloquentModel;

final readonly class EloquentPrivacyRequestRepository implements PrivacyRequestRepositoryPort
{
    public function recordResolved(string $subjectRef, PrivacyRequestType $type, DateTimeImmutable $at): void
    {
        ScoutPrivacyRequestEloquentModel::query()->create([
            'subject_ref' => $subjectRef,
            'request_type' => $type->value,
            'received_at' => $at,
            'resolved_at' => $at,
            'outcome' => PrivacyRequestOutcome::Resolved->value,
        ]);
    }
}
