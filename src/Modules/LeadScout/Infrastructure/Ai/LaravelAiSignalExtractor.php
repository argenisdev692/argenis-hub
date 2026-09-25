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
 * excerpt appears literally in the stored source text AND — for commercial
 * capacity signals — the excerpt carries semantic support for the claim
 * (not just a coincident literal match). Sitemap/URL-list excerpts never
 * prove a commercial signal. Anything else is discarded and counted.
 * Provider/model resolve from the extraction catalog with fallback; real
 * token usage prices the AI budget.
 */
final readonly class LaravelAiSignalExtractor implements SignalExtractorPort
{
    /**
     * Semantic stems a commercial excerpt must contain to support the key.
     * Lower-cased; matched with whole-word/prefix regexes (spec 5c).
     *
     * @var array<string, list<string>>
     */
    private const array SEMANTIC_CUES = [
        'freelance_contract' => ['freelanc', 'contractor', 'autónomo', 'autonomo', 'recibos verdes', 'extern'],
        'accepts_external' => ['freelanc', 'partner', 'colabor', 'subcontrat', 'marca blanca', 'white label', 'extern', 'parceir'],
    ];

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
            $sourceUrl = self::webUrlOrEmpty((string) ($candidate['source_url'] ?? ''));

            if (! SignalKey::allowed($key) || $excerpt === '' || ! str_contains($sourceText, $excerpt)) {
                $discarded++;

                continue;
            }

            // Sitemap/URL-list excerpts prove navigation structure, never a
            // commercial buying signal (spec 5c).
            if (self::isSitemapOrUrlList($excerpt, $sourceUrl)) {
                $discarded++;

                continue;
            }

            $nature = ($candidate['nature'] ?? '') === 'inference' ? 'inference' : 'fact';

            // Commercial capacity claims need semantic support in the excerpt
            // itself: the signal terms must appear, not just any coincident
            // literal match (spec 5c). Commercial facts additionally need a
            // real evidence URL — a fact without a source is a phantom.
            if (isset(self::SEMANTIC_CUES[$key]) && ! self::supportsSemantically($key, $excerpt)) {
                $discarded++;

                continue;
            }

            if ($nature === 'fact' && isset(self::SEMANTIC_CUES[$key]) && $sourceUrl === '') {
                $discarded++;

                continue;
            }

            $signals[] = [
                'signal_key' => $key,
                'nature' => $nature,
                'excerpt' => mb_substr($excerpt, 0, 2000),
                'confidence' => max(0, min(100, (int) ($candidate['confidence'] ?? 50))),
                'source_url' => $sourceUrl,
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

    private static function supportsSemantically(string $key, string $excerpt): bool
    {
        $haystack = mb_strtolower($excerpt);

        foreach (self::SEMANTIC_CUES[$key] ?? [] as $cue) {
            $pattern = '/(?<![\p{L}\p{N}_])'.preg_quote(mb_strtolower($cue), '/').'/u';

            if (preg_match($pattern, $haystack) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sitemap XML, sitemap URLs and bare URL lists carry no commercial
     * meaning: `<loc>`/`<lastmod>` payloads and multi-URL dumps are
     * rejected as evidence for any signal, commercial or not.
     */
    private static function isSitemapOrUrlList(string $excerpt, string $sourceUrl): bool
    {
        $lower = mb_strtolower($excerpt);

        if (str_contains($lower, '<loc>') || str_contains($lower, '<lastmod>') || str_contains($lower, '<urlset')) {
            return true;
        }

        if (preg_match('~sitemap.*\.xml(\?.*)?$~i', $sourceUrl) === 1) {
            return true;
        }

        if (preg_match_all('~https?://[^\s)]+~i', $excerpt, $urls) > 0 && count($urls[0]) >= 3) {
            $words = preg_split('/\s+/', trim((string) preg_replace('~https?://[^\s)]+~i', ' ', $excerpt))) ?: [];

            if (count(array_filter($words)) < 20) {
                return true;
            }
        }

        return false;
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
