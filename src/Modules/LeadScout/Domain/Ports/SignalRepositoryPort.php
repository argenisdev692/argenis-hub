<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\Entities\Signal;
use Modules\LeadScout\Domain\ValueObjects\NewSignal;

interface SignalRepositoryPort
{
    /**
     * @return list<Signal> oldest first
     */
    public function forCompany(int $companyId): array;

    /**
     * Stores the signal unless the company already has one with its key.
     *
     * @return bool true when stored
     */
    public function addIfAbsent(int $companyId, NewSignal $signal): bool;
}
