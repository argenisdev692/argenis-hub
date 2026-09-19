<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Ai;

use Modules\LeadScout\Domain\Ports\DraftWriterPort;

/**
 * Draft opener writer (spec US-10, T062): catalog-resolved provider/model
 * (or the selector override), fallback on failure, real usage priced.
 * Receives evidence + public proof summary only — never the CV (FR-35).
 */
final readonly class LaravelAiDraftWriter implements DraftWriterPort
{
    private const string FALLBACK_OPENER = 'I work with agencies like yours as a white-label Laravel contractor.';

    public function __construct(private MeteredAiCall $metered) {}

    /**
     * @return array{opener: string, provider: string, model: string}
     */
    #[\NoDiscard]
    public function write(
        string $prompt,
        ?string $provider = null,
        ?string $model = null,
    ): array {
        // Planning estimate ~4k in / ~500 out; real usage settles the ledger.
        $call = $this->metered->generate('drafting', WriteOutreachOpenerAgent::class, $prompt, $provider, $model, 90, 4000, 500);

        $opener = trim((string) ($call['response']['opener'] ?? ''));

        return [
            'opener' => $opener !== '' ? mb_substr($opener, 0, 500) : self::FALLBACK_OPENER,
            'provider' => $call['provider'],
            'model' => $call['model'],
        ];
    }
}
