<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

use Modules\LeadScout\Domain\Entities\Signal;
use Modules\LeadScout\Domain\Enums\MessageVariant;
use Modules\LeadScout\Domain\Enums\SignalDimension;
use Modules\LeadScout\Domain\ValueObjects\DraftContext;

/**
 * Deterministic draft choices (spec US-5): which message variant, which
 * language and which signal a draft leans on. Pure — no I/O.
 */
final readonly class DraftPlanner
{
    private const array STACK_KEYS = ['laravel', 'vue_inertia', 'livewire', 'stack_db'];

    /** Latin-American markets written to in Spanish. */
    private const array SPANISH_COUNTRIES = ['BR', 'MX', 'AR', 'UY', 'CL', 'CO'];

    #[\NoDiscard]
    public function variant(DraftContext $context): MessageVariant
    {
        $keys = $context->signalKeys();

        return match (true) {
            $context->hasOffer || in_array('active_vacancy', $keys, true) => MessageVariant::Vacancy,
            array_intersect($keys, self::STACK_KEYS) !== [] => MessageVariant::Stack,
            in_array('maintenance_sla', $keys, true) || in_array('long_term', $keys, true) => MessageVariant::Legacy,
            $context->company->sectors !== [] => MessageVariant::Sector,
            default => MessageVariant::Stack,
        };
    }

    #[\NoDiscard]
    public function language(?string $country): string
    {
        return match (true) {
            $country === 'PT' => 'pt',
            $country === 'ES', in_array($country, self::SPANISH_COUNTRIES, true) => 'es',
            default => 'en',
        };
    }

    /**
     * Technologies the company is seen using (technical signal values).
     *
     * @param  list<Signal>  $signals
     * @return list<string>
     */
    #[\NoDiscard]
    public function companyTechs(array $signals): array
    {
        return array_values(array_map(
            static fn (Signal $signal): string => (string) $signal->valueText,
            array_filter(
                $signals,
                static fn (Signal $signal): bool => $signal->dimension === SignalDimension::Technical && $signal->valueText !== null,
            ),
        ));
    }

    /**
     * The signal the draft leans on; an explicit job posting wins.
     */
    #[\NoDiscard]
    public function signalUsed(DraftContext $context, string $variant, ?string $jobPostingId): ?string
    {
        if ($jobPostingId !== null) {
            return $jobPostingId;
        }

        $keys = $context->signalKeys();
        $preferred = match ($variant) {
            'vacancy' => 'active_vacancy',
            'stack' => 'laravel',
            'legacy' => 'maintenance_sla',
            default => null,
        };

        return $preferred !== null && in_array($preferred, $keys, true) ? $preferred : ($keys[0] ?? null);
    }

    /**
     * Warning stored on the draft: the recommended channel's own caveat,
     * else the first reason a channel was blocked.
     *
     * @param  array{ranked: list<array{uuid: ?string, blocked_reason: ?string, warning: ?string}>}  $advice
     */
    #[\NoDiscard]
    public function channelWarning(array $advice, ?string $recommendedUuid): ?string
    {
        foreach ($advice['ranked'] as $candidate) {
            if ($candidate['uuid'] === $recommendedUuid) {
                return $candidate['warning'] ?? $candidate['blocked_reason'];
            }
        }

        foreach ($advice['ranked'] as $candidate) {
            if ($candidate['blocked_reason'] !== null) {
                return $candidate['blocked_reason'];
            }
        }

        return 'No permitted channel: manual review required.';
    }
}
