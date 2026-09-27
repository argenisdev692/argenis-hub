<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

/**
 * Closed AI catalog (spec US-10, FR-22): fixed provider/model IDs, live
 * availability from credentials, estimated cost per 100 uses, and
 * purpose resolution (stored default + optional per-draft override).
 * The frontend never sends free text.
 *
 * "Closed" governs what a REQUEST may name, not what the operator may
 * configure: the server-side default may be any model id, so a new model
 * ships as an env change. Such a model still needs a price, which is what
 * {@see self::rates()} guarantees — never zero.
 */
interface AiModelCatalogPort
{
    /**
     * @return list<array{provider: string, model: string, label: string, available: bool, unavailable_reason: ?string, est_cost_per_100_usd: float, price_expired: bool, active_uncatalogued?: bool}>
     */
    public function options(string $purpose): array;

    /**
     * @return array{provider: string, model: string, fallback_provider: ?string, fallback_model: ?string}
     */
    public function resolve(string $purpose, ?string $provider = null, ?string $model = null): array;

    public function available(string $provider): bool;

    /**
     * Per-MTok USD rates for a model, falling back to the paid-tier default
     * when the id is not in the catalog. A zero rate would disable the budget.
     *
     * @return array{input_per_mtok_usd: float, output_per_mtok_usd: float}
     */
    public function rates(string $model): array;
}
