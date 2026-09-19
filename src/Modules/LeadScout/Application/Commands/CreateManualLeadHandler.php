<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Modules\LeadScout\Application\DTOs\CreateManualLeadData;
use Modules\LeadScout\Domain\Entities\Company;
use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Exceptions\InvalidInputException;
use Modules\LeadScout\Domain\Exceptions\SuppressedException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\SuppressionRepositoryPort;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;

/**
 * Manual lead intake (spec FR-20, T029): company + URL + note for the
 * manual-prospecting phase and referrals. Suppressed domains answer 409 —
 * the gate prevails over manual entry (spec FR-43).
 */
final readonly class CreateManualLeadHandler
{
    public function __construct(
        private CompanyRepositoryPort $companies,
        private SuppressionGate $gate,
        private SuppressionRepositoryPort $suppressions,
    ) {}

    public function handle(CreateManualLeadData $data, int $userId): Company
    {
        try {
            $domain = CanonicalDomain::fromUrl(trim($data->url))->value;
        } catch (\InvalidArgumentException $e) {
            throw InvalidInputException::withMessages(['url' => $e->getMessage()]);
        }

        $this->assertNotSuppressed($domain, $data->name);

        $existing = $this->companies->byDomain($domain);

        if ($existing !== null) {
            return $existing;
        }

        return $this->companies->register(
            canonicalDomain: $domain,
            name: mb_substr(trim($data->name), 0, 255),
            origin: CompanyOrigin::Manual,
            originRef: $data->note === null ? null : mb_substr(trim($data->note), 0, 255),
        );
    }

    private function assertNotSuppressed(string $domain, string $name): void
    {
        $candidates = $this->suppressions->matching($domain, null, $name);

        if ($this->gate->isSuppressed($domain, null, $name, $candidates)) {
            throw new SuppressedException;
        }
    }
}
