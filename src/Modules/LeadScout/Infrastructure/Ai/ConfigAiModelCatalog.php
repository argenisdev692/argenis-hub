<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Ai;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\LeadScout\Domain\Ports\AiModelCatalogPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutAiSettingEloquentModel;

/**
 * Config-driven catalog with DB overrides (spec US-10, T054). Defaults are
 * seeded from `.env`; the web UI edits the DB rows, never the server config.
 * A provider without credentials reports `available: false` and is
 * rejected when chosen (422).
 */
final readonly class ConfigAiModelCatalog implements AiModelCatalogPort
{
    /**
     * @return list<array{provider: string, model: string, label: string, available: bool, unavailable_reason: ?string, est_cost_per_100_usd: float, price_expired: bool}>
     */
    public function options(string $purpose): array
    {
        $this->assertPurpose($purpose);
        $options = [];

        foreach ((array) config('lead-scout.ai_catalog', []) as $modelKey => $entry) {
            $provider = (string) ($entry['provider'] ?? '');
            $available = $this->available($provider);
            $validUntil = $entry['price_valid_until'] ?? null;

            $options[] = [
                'provider' => $provider,
                'model' => (string) $modelKey,
                'label' => (string) ($entry['label'] ?? $modelKey),
                'available' => $available,
                'unavailable_reason' => $available ? null : "Missing credentials for {$provider}.",
                'est_cost_per_100_usd' => $this->estimatePer100($entry),
                'price_expired' => is_string($validUntil) && CarbonImmutable::parse($validUntil)->isPast(),
            ];
        }

        return $options;
    }

    public function resolve(string $purpose, ?string $provider = null, ?string $model = null): array
    {
        $this->assertPurpose($purpose);

        $stored = ScoutAiSettingEloquentModel::query()->where('purpose', $purpose)->first();
        $defaults = (array) config("lead-scout.ai_defaults.{$purpose}", []);

        $resolved = [
            'provider' => $provider ?? $stored?->provider ?? $defaults['provider'] ?? null,
            'model' => $model ?? $stored?->model ?? $defaults['model'] ?? null,
            'fallback_provider' => $stored?->fallback_provider ?? $defaults['fallback_provider'] ?? null,
            'fallback_model' => $stored?->fallback_model ?? $defaults['fallback_model'] ?? null,
        ];

        $catalog = (array) config('lead-scout.ai_catalog', []);

        if (! is_string($resolved['model']) || ! isset($catalog[$resolved['model']])) {
            throw ValidationException::withMessages(['model' => 'Model is outside the allowed catalog.']);
        }

        if (! is_string($resolved['provider']) || ($catalog[$resolved['model']]['provider'] ?? null) !== $resolved['provider']) {
            throw ValidationException::withMessages(['provider' => 'Provider does not serve this model.']);
        }

        if (! $this->available($resolved['provider'])) {
            throw ValidationException::withMessages(['provider' => "Provider {$resolved['provider']} has no credentials configured."]);
        }

        return $resolved;
    }

    public function available(string $provider): bool
    {
        return filled(config("ai.providers.{$provider}.key"));
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function estimatePer100(array $entry): float
    {
        // Planning assumption: ~8k input + ~1k output tokens per call.
        $input = (float) ($entry['input_per_mtok_usd'] ?? 0);
        $output = (float) ($entry['output_per_mtok_usd'] ?? 0);

        return round(($input * 8000 + $output * 1000) / 1_000_000 * 100, 4);
    }

    private function assertPurpose(string $purpose): void
    {
        if (! in_array($purpose, ['extraction', 'drafting'], true)) {
            throw ValidationException::withMessages(['purpose' => 'Unknown AI purpose.']);
        }
    }
}
