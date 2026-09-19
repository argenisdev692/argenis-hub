<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Queries;

use Modules\LeadScout\Domain\Entities\Profile;
use Modules\LeadScout\Domain\Exceptions\ProfileNotFoundException;
use Modules\LeadScout\Domain\Ports\ProfileRepositoryPort;

/**
 * The operator's current matching profile (spec US-1).
 */
final readonly class GetProfileHandler
{
    public function __construct(private ProfileRepositoryPort $profiles) {}

    public function handle(int $userId): Profile
    {
        return $this->profiles->current($userId) ?? throw new ProfileNotFoundException;
    }
}
