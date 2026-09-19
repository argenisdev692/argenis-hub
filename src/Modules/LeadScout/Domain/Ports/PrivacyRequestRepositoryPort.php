<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Enums\PrivacyRequestType;

/**
 * Accountability ledger of data-subject requests (spec FR-29). Stores a
 * hashed subject reference only — never the person's data.
 */
interface PrivacyRequestRepositoryPort
{
    public function recordResolved(string $subjectRef, PrivacyRequestType $type, DateTimeImmutable $at): void;
}
