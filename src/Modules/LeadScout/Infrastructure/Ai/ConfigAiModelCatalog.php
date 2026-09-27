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
     * Options for the settings selector.
     *
     * Catalogued models, plus the model's own currently-active id when that id
     * is NOT catalogued. Without that second entry the dialog renders an empty
     * select for an operator who set a new model in `.env`, and saving the form
     * looks broken. The extra entry is marked so the UI can label it as the
     * active-but-uncatalogued model instead of offering it as a choice.
     *
     * @return list<array{provider: string, model: string, label: string, available: bool, unavailable_reason: ?string, est_cost_per_100_usd: float, price_expired: bool, active_uncatalogued?: bool}>
     */
    public function options(string $purpose): array
    {
        $this->assertPurpose($purpose);
        $options = [];
        $catalog = $this->catalog();

        foreach ($catalog as $modelKey => $entry) {
            $provider = (string) ($entry['provider'] ?? '');
            $available = $this->available($provider);
            $validUntil = $entry['price_valid_until'] ?? null;

            $options[] = [
                'provider' => $provider,
                'model' => (string) $modelKey,
                'label' => (string) ($entry['label'] ?? $modelKey),
                'available' => $available,
                'unavailable_reason' => $available ? null : "Missing credentials for {$provider}.",
                'est_cost_per_100_usd' => $this->estimatePer100($this->rates((string) $modelKey)),
                'price_expired' => is_string($validUntil) && CarbonImmutable::parse($validUntil)->isPast(),
            ];
        }

        $active = $this->activeModel($purpose);

        if ($active !== null && ! isset($catalog[$active['model']])) {
            $options[] = [
                'provider' => $active['provider'],
                'model' => $active['model'],
                'label' => $active['model'].' (active, not in catalog)',
                'available' => $this->available($active['provider']),
                'unavailable_reason' => null,
                'est_cost_per_100_usd' => $this->estimatePer100($this->rates($active['model'])),
                'price_expired' => false,
                'active_uncatalogued' => true,
            ];
        }

        return $options;
    }

    /**
     * The model this purpose currently resolves to, without validating it.
     *
     * @return array{provider: string, model: string}|null
     */
    private function activeModel(string $purpose): ?array
    {
        $stored = ScoutAiSettingEloquentModel::query()->where('purpose', $purpose)->first();
        $defaults = (array) config("lead-scout.ai_defaults.{$purpose}", []);

        $model = $stored?->model ?? $defaults['model'] ?? null;
        $provider = $stored?->provider ?? $defaults['provider'] ?? null;

        return is_string($model) && $model !== '' && is_string($provider)
            ? ['model' => $model, 'provider' => $provider]
            : null;
    }

    /**
     * Resolve the provider/model for a purpose.
     *
     * Two callers with deliberately different strictness:
     *
     *  - **Server default** (`$provider`/`$model` null): the operator's own
     *    `.env`. Accepts any model id, catalogued or not, so a new model ships
     *    as a `.env` line instead of a PHP edit. An uncatalogued model is
     *    priced by {@see self::rates()} at the paid-tier default, so the
     *    budget guard still means something.
     *  - **Explicit request** (the settings UI): the model must be catalogued,
     *    or already the value this purpose currently resolves to. That
     *    last clause is what lets the dialog round-trip an uncatalogued
     *    default instead of 422-ing on save, while still refusing to let a
     *    request inject a novel model.
     *
     * @throws ValidationException
     */
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

        $catalogued = is_string($resolved['model']) && isset($this->catalog()[$resolved['model']]);

        if (! is_string($resolved['model']) || $resolved['model'] === '') {
            throw ValidationException::withMessages(['model' => 'Model is outside the allowed catalog.']);
        }

        if (! $catalogued && ! $this->acceptableWithoutCatalog($purpose, $model, $resolved['model'])) {
            throw ValidationException::withMessages(['model' => 'Model is outside the allowed catalog.']);
        }

        // Provider is only checkable against the catalog when the model is in
        // it. An uncatalogued id comes from the operator's env, so the provider
        // that ships with it is trusted as configured.
        if ($catalogued) {
            $catalogProvider = (string) ($this->catalog()[$resolved['model']]['provider'] ?? '');

            if ($catalogProvider !== $resolved['provider']) {
                throw ValidationException::withMessages(['provider' => 'Provider does not serve this model.']);
            }
        }

        $resolvedProvider = $resolved['provider'];

        if (! is_string($resolvedProvider) || ! $this->available($resolvedProvider)) {
            $name = is_string($resolvedProvider) && $resolvedProvider !== '' ? $resolvedProvider : 'unknown';

            throw ValidationException::withMessages(['provider' => "Provider {$name} has no credentials configured."]);
        }

        return $resolved;
    }

    /**
     * Whether an uncatalogued model id is still legitimate.
     *
     * Null `$requested` means the server is resolving its own default, so the
     * catalog does not apply. Otherwise the id is only tolerated when it is
     * already the one this purpose resolves to — that is the settings dialog
     * re-saving the active value, not a caller choosing a new model.
     */
    private function acceptableWithoutCatalog(string $purpose, ?string $requested, string $candidate): bool
    {
        if ($requested === null) {
            return true;
        }

        $stored = ScoutAiSettingEloquentModel::query()->where('purpose', $purpose)->first();
        $current = $stored?->model ?? config("lead-scout.ai_defaults.{$purpose}.model");

        return $candidate === $current;
    }

    /**
     * Price per Mtok for a model, falling back to the paid-tier default.
     *
     * The fallback is the load-bearing part: an unknown model used to price at
     * zero, which silently disables the `ai` budget for that model. Defaulting
     * high makes the budget trip early, and refusing work is recoverable.
     *
     * @return array{input_per_mtok_usd: float, output_per_mtok_usd: float}
     */
    #[\NoDiscard]
    public function rates(string $model): array
    {
        $entry = (array) ($this->catalog()[$model] ?? []);
        $fallback = (array) config('lead-scout.ai_pricing_default', []);

        return [
            'input_per_mtok_usd' => (float) ($entry['input_per_mtok_usd'] ?? $fallback['input_per_mtok_usd'] ?? 0.0),
            'output_per_mtok_usd' => (float) ($entry['output_per_mtok_usd'] ?? $fallback['output_per_mtok_usd'] ?? 0.0),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function catalog(): array
    {
        return (array) config('lead-scout.ai_catalog', []);
    }

    public function available(string $provider): bool
    {
        return filled(config("ai.providers.{$provider}.key"));
    }

    /**
     * @param  array{input_per_mtok_usd: float, output_per_mtok_usd: float}  $rates
     */
    private function estimatePer100(array $rates): float
    {
        // Planning assumption: ~8k input + ~1k output tokens per call.
        $input = (float) $rates['input_per_mtok_usd'];
        $output = (float) $rates['output_per_mtok_usd'];

        return round(($input * 8000 + $output * 1000) / 1_000_000 * 100, 4);
    }

    private function assertPurpose(string $purpose): void
    {
        if (! in_array($purpose, ['extraction', 'drafting'], true)) {
            throw ValidationException::withMessages(['purpose' => 'Unknown AI purpose.']);
        }
    }
}
