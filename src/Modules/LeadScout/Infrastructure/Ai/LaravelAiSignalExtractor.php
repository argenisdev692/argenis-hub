<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Ai;

use Modules\LeadScout\Domain\Ports\SignalExtractorPort;
use Modules\LeadScout\Domain\ValueObjects\SignalKey;
use Uri\WhatWg\InvalidUrlException;
use Uri\WhatWg\Url;

/**
 * Verified LLM extraction (spec FR-10, T056): the agent proposes, the code
 * disposes. A signal persists only when its key is allowlisted AND its
 * excerpt appears literally in the stored source text — anything else is
 * discarded and counted. Provider/model resolve from the extraction
 * catalog with fallback; real token usage prices the AI budget.
 */
final readonly class LaravelAiSignalExtractor implements SignalExtractorPort
{
    public function __construct(private MeteredAiCall $metered) {}

    /**
     * @return array{signals: list<array{signal_key: string, nature: string, excerpt: string, confidence: int, source_url: string}>, discarded: int, provider: string, model: string, company_type: ?string, team_size_observed: ?int}
     */
    #[\NoDiscard]
    public function extract(
        string $prompt,
        string $sourceText,
        ?string $provider = null,
        ?string $model = null,
    ): array {
        // Planning estimate ~8k in / ~1k out; real usage settles the ledger.
        $call = $this->metered->generate('extraction', ExtractCompanySignalsAgent::class, $prompt, $provider, $model, 120, 8000, 1000);

        return $this->verified($call['response'], $sourceText, $call['provider'], $call['model']);
    }

    /**
     * @return array{signals: list<array{signal_key: string, nature: string, excerpt: string, confidence: int, source_url: string}>, discarded: int, provider: string, model: string, company_type: ?string, team_size_observed: ?int}
     */
    private function verified(mixed $response, string $sourceText, string $provider, string $model): array
    {
        $signals = [];
        $discarded = 0;

        foreach ((array) ($response['signals'] ?? []) as $candidate) {
            $key = (string) ($candidate['signal_key'] ?? '');
            $excerpt = (string) ($candidate['excerpt'] ?? '');

            if (! SignalKey::allowed($key) || $excerpt === '' || ! str_contains($sourceText, $excerpt)) {
                $discarded++;

                continue;
            }

            $nature = ($candidate['nature'] ?? '') === 'inference' ? 'inference' : 'fact';

            $signals[] = [
                'signal_key' => $key,
                'nature' => $nature,
                'excerpt' => mb_substr($excerpt, 0, 2000),
                'confidence' => max(0, min(100, (int) ($candidate['confidence'] ?? 50))),
                'source_url' => self::webUrlOrEmpty((string) ($candidate['source_url'] ?? '')),
            ];
        }

        $companyType = (string) ($response['company_type'] ?? '');
        $teamSize = $response['team_size_observed'] ?? null;

        return [
            'signals' => $signals,
            'discarded' => $discarded,
            'provider' => $provider,
            'model' => $model,
            'company_type' => in_array($companyType, ['software_agency', 'consultancy', 'product_company', 'recruiter', 'large_outsourcer', 'other'], true) ? $companyType : null,
            'team_size_observed' => is_numeric($teamSize) ? (int) $teamSize : null,
        ];
    }

    /**
     * LLM output is untrusted (OWASP LLM05): the evidence URL is rendered as a
     * clickable link, so anything but an absolute http(s) URL is dropped —
     * a prompt-injected `javascript:` value must never reach the bandeja.
     */
    private static function webUrlOrEmpty(string $candidate): string
    {
        $candidate = mb_substr(trim($candidate), 0, 2048);

        try {
            $scheme = new Url($candidate)->getScheme();
        } catch (InvalidUrlException) {
            return '';
        }

        return in_array($scheme, ['http', 'https'], true) ? $candidate : '';
    }
}
