<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Entities\Suppression;

/**
 * Single choke point answering «may this company be touched?» (spec FR-17,
 * FR-43, clarify A18). Pure decision over candidate entries: domain, tax ID
 * (DGC cross-check) or normalized name. A hit suppresses across every
 * entry — discovery, import, manual, offers, enrichment, score, draft, sent.
 */
final readonly class SuppressionGate
{
    /** A DGC list older than this no longer protects PT email (T074). */
    private const string DGC_MAX_AGE = '-3 months';

    /**
     * @param  list<Suppression>  $candidates
     */
    #[\NoDiscard]
    public function match(?string $domain, ?string $taxId, ?string $name, array $candidates): ?Suppression
    {
        $name = $name === null ? null : mb_strtolower(trim($name));

        foreach ($candidates as $candidate) {
            if ($domain !== null && $candidate->canonicalDomain !== null && $candidate->canonicalDomain === $domain) {
                return $candidate;
            }

            if ($taxId !== null && $candidate->taxId !== null && $candidate->taxId === $taxId) {
                return $candidate;
            }

            if ($name !== null && $name !== '' && $candidate->name !== null && mb_strtolower(trim($candidate->name)) === $name) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  list<Suppression>  $candidates
     */
    public function isSuppressed(?string $domain, ?string $taxId, ?string $name, array $candidates): bool
    {
        return $this->match($domain, $taxId, $name, $candidates) !== null;
    }

    public function dgcListIsStale(?DateTimeImmutable $latestImportAt, DateTimeImmutable $now): bool
    {
        return $latestImportAt === null || $latestImportAt < $now->modify(self::DGC_MAX_AGE);
    }
}
