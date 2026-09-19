<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Modules\LeadScout\Application\DTOs\SuppressData;
use Modules\LeadScout\Domain\Entities\Suppression;
use Modules\LeadScout\Domain\Enums\SuppressionSource;
use Modules\LeadScout\Domain\Exceptions\InvalidInputException;
use Modules\LeadScout\Domain\Ports\SuppressionRepositoryPort;
use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;

/**
 * Manual suppression (spec FR-17, T029). Idempotent: re-suppressing the
 * same domain returns the existing row.
 */
final readonly class SuppressCompanyHandler
{
    public function __construct(private SuppressionRepositoryPort $suppressions) {}

    public function handle(SuppressData $data): Suppression
    {
        // Bare domains travel the same normalization as posting URLs
        // (scheme assumed, `www.` stripped) so `WWW.Acme.ES` and
        // `https://acme.es` suppress the same company exactly once.
        $input = trim($data->domain);

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $input) !== 1) {
            $input = 'https://'.$input;
        }

        try {
            $domain = CanonicalDomain::fromUrl($input)->value;
        } catch (\InvalidArgumentException $e) {
            throw InvalidInputException::withMessages(['domain' => $e->getMessage()]);
        }

        return $this->suppressions->suppressDomain(
            $domain,
            SuppressionSource::Manual,
            mb_substr(trim($data->reason), 0, 500),
        );
    }
}
