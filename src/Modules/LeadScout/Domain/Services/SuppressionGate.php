<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

/**
 * Single choke point answering «may this company be touched?» (spec FR-17,
 * FR-43, clarify A18). Pure decision over candidate rows: domain, tax ID
 * (DGC cross-check) or normalized name. A hit suppresses across every
 * entry — discovery, import, manual, offers, enrichment, score, draft, sent.
 *
 * Domain-pure by design: candidates arrive as plain arrays so this service
 * never imports Eloquent (layer rule). Repositories map rows to arrays.
 */
final readonly class SuppressionGate
{
    /**
     * @param  array<int, array{canonical_domain: ?string, tax_id: ?string, name: ?string}>  $candidates
     * @return array{canonical_domain: ?string, tax_id: ?string, name: ?string}|null
     */
    #[\NoDiscard]
    public function match(?string $domain, ?string $taxId, ?string $name, array $candidates): ?array
    {
        $name = $name === null ? null : mb_strtolower(trim($name));

        foreach ($candidates as $candidate) {
            if ($domain !== null && ($candidate['canonical_domain'] ?? null) !== null && $candidate['canonical_domain'] === $domain) {
                return $candidate;
            }

            if ($taxId !== null && ($candidate['tax_id'] ?? null) !== null && $candidate['tax_id'] === $taxId) {
                return $candidate;
            }

            if ($name !== null && $name !== '' && ($candidate['name'] ?? null) !== null && mb_strtolower(trim($candidate['name'])) === $name) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{canonical_domain: ?string, tax_id: ?string, name: ?string}>  $candidates
     */
    public function isSuppressed(?string $domain, ?string $taxId, ?string $name, array $candidates): bool
    {
        return $this->match($domain, $taxId, $name, $candidates) !== null;
    }
}
