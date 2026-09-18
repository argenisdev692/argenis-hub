<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

/**
 * Closed AI catalog (spec US-10, FR-22): fixed provider/model IDs, live
 * availability from credentials, estimated cost per 100 uses, and
 * purpose resolution (stored default + optional per-draft override).
 * The frontend never sends free text.
 */
interface AiModelCatalogPort
{
    /**
     * @return list<array{provider: string, model: string, label: string, available: bool, unavailable_reason: ?string, est_cost_per_100_usd: float, price_expired: bool}>
     */
    public function options(string $purpose): array;

    /**
     * @return array{provider: string, model: string, fallback_provider: ?string, fallback_model: ?string}
     */
    public function resolve(string $purpose, ?string $provider = null, ?string $model = null): array;

    public function available(string $provider): bool;
}
