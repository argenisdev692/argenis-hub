<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Modules\LeadScout\Domain\Enums\ExtractionMethod;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Services\PersonalDataScrubber;
use Modules\LeadScout\Domain\Services\RuleBasedSignalExtractor;
use Modules\LeadScout\Infrastructure\Ai\LaravelAiSignalExtractor;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;

/**
 * Two-pass signal extraction (spec FR-6/FR-10, T057): deterministic rules
 * first, then the LLM ONLY for dimensions rules cannot resolve
 * (commercial, recurrent, company type, communication gaps). Team pages
 * never reach the model and every prompt is scrubbed of PII and decisor
 * names first (FR-25); every returned excerpt is verified literally.
 *
 * @return array{rules: int, ai: int, discarded: int, provider: ?string, model: ?string}
 */
final readonly class ExtractSignalsHandler
{
    /**
     * @var list<string>
     */
    private const array LLM_DIMENSIONS = ['commercial', 'recurrent', 'communication'];

    public function __construct(
        private RuleBasedSignalExtractor $rules,
        private LaravelAiSignalExtractor $ai,
        private PersonalDataScrubber $scrubber,
        private ExtractDecisionMakersHandler $decisors,
    ) {}

    public function handle(string $companyUuid): array
    {
        $company = ScoutCompanyEloquentModel::query()->where('uuid', $companyUuid)->first()
            ?? throw new CompanyNotFoundException($companyUuid);

        $pages = $company->fetchedPages()
            ->whereNotNull('content_markdown')
            ->orderBy('id')
            ->get(['url', 'page_type', 'content_markdown'])
            ->map(static fn ($page): array => [
                'url' => $page->url,
                'page_type' => $page->page_type?->value,
                'markdown' => (string) $page->content_markdown,
            ])
            ->all();

        $postings = $company->postings()
            ->where('status', 'active')
            ->orderByDesc('published_at')
            ->get(['title', 'body_text', 'remote_mode', 'contract_type', 'language', 'published_at', 'status', 'source_url'])
            ->map(static fn ($posting): array => [
                'title' => $posting->title,
                'body' => $posting->body_text,
                'remote_mode' => $posting->remote_mode->value,
                'contract_type' => $posting->contract_type->value,
                'language' => $posting->language,
                'published_at' => $posting->published_at?->toDateTimeString(),
                'status' => $posting->status->value,
                'source_url' => $posting->source_url,
            ])
            ->all();

        $report = ['rules' => 0, 'ai' => 0, 'discarded' => 0, 'provider' => null, 'model' => null];

        $report['rules'] = $this->persistRuleSignals($company, $pages, $postings);

        if ($this->needsAi($company)) {
            $ai = $this->runAi($company, $pages);
            $report['ai'] = $ai['kept'];
            $report['discarded'] = $ai['discarded'];
            $report['provider'] = $ai['provider'];
            $report['model'] = $ai['model'];
        }

        return $report;
    }

    /**
     * @param  array<int, array{url: string, page_type: ?string, markdown: string}>  $pages
     * @param  array<int, array{title: string, body: ?string, remote_mode: string, contract_type: string, language: ?string, published_at: ?string, status: string, source_url: string}>  $postings
     */
    private function persistRuleSignals(ScoutCompanyEloquentModel $company, array $pages, array $postings): int
    {
        $existing = $company->signals()->pluck('signal_key')->all();

        $signals = $this->rules->extract(
            ['country' => $company->country, 'canonical_domain' => $company->canonical_domain],
            array_map(static fn (array $page): array => [
                'url' => $page['url'], 'markdown' => $page['markdown'], 'fetched_at' => now()->toDateTimeString(),
            ], $pages),
            $postings,
        );

        $kept = 0;

        foreach ($signals as $signal) {
            if (in_array($signal['signal_key'], $existing, true)) {
                continue;
            }

            $company->signals()->create([
                'dimension' => $signal['dimension'],
                'signal_key' => $signal['signal_key'],
                'value_text' => $signal['value_text'],
                'nature' => $signal['nature'],
                'confidence' => $signal['confidence'],
                'evidence_url' => $signal['evidence_url'],
                'evidence_excerpt' => $signal['evidence_excerpt'],
                'captured_at' => $signal['captured_at'],
                'extraction_method' => ExtractionMethod::Rule->value,
            ]);
            $existing[] = $signal['signal_key'];
            $kept++;
        }

        return $kept;
    }

    private function needsAi(ScoutCompanyEloquentModel $company): bool
    {
        $dimensions = $company->signals()->pluck('dimension')
            ->map(static fn ($dimension): string => $dimension instanceof \BackedEnum ? $dimension->value : (string) $dimension)
            ->all();

        foreach (self::LLM_DIMENSIONS as $dimension) {
            if (! in_array($dimension, $dimensions, true)) {
                return true;
            }
        }

        return $company->company_type === null;
    }

    /**
     * @param  array<int, array{url: string, page_type: ?string, markdown: string}>  $pages
     * @return array{kept: int, discarded: int, provider: ?string, model: ?string}
     */
    private function runAi(ScoutCompanyEloquentModel $company, array $pages): array
    {
        // Team pages never reach the model (FR-25, T057).
        $usable = array_values(array_filter(
            $pages,
            static fn (array $page): bool => $page['page_type'] !== 'team' && trim($page['markdown']) !== '',
        ));

        if ($usable === []) {
            return ['kept' => 0, 'discarded' => 0, 'provider' => null, 'model' => null];
        }

        $names = array_column($this->decisors->candidates($company->uuid), 'name');

        $blocks = [];
        $sourceText = '';

        foreach ($usable as $page) {
            $clean = $this->scrubber->scrub($page['markdown'], $names);
            $blocks[] = "--- PAGE {$page['url']} ---\n{$clean}";
            $sourceText .= "\n".$page['markdown'];
        }

        $prompt = "Extract buying signals for the software company below.\n\n".implode("\n\n", $blocks);

        $result = $this->ai->extract($prompt, $sourceText);

        $existing = $company->signals()->pluck('signal_key')->all();
        $kept = 0;

        foreach ($result['signals'] as $signal) {
            if (in_array($signal['signal_key'], $existing, true)) {
                continue;
            }

            $dimension = $this->dimensionOf($signal['signal_key']);

            $company->signals()->create([
                'dimension' => $dimension,
                'signal_key' => $signal['signal_key'],
                'nature' => $signal['nature'],
                'confidence' => $signal['confidence'],
                'evidence_url' => $signal['source_url'] !== '' ? $signal['source_url'] : null,
                'evidence_excerpt' => $signal['excerpt'],
                'captured_at' => now(),
                'extraction_method' => ExtractionMethod::Ai->value,
                'ai_provider' => $result['provider'],
                'ai_model' => $result['model'],
            ]);
            $existing[] = $signal['signal_key'];
            $kept++;
        }

        if ($company->company_type === null && $result['company_type'] !== null) {
            $company->update(['company_type' => $result['company_type']]);
        }

        if ($company->team_size_observed === null && $result['team_size_observed'] !== null) {
            $company->update(['team_size_observed' => $result['team_size_observed']]);
        }

        return [
            'kept' => $kept,
            'discarded' => $result['discarded'],
            'provider' => $result['provider'],
            'model' => $result['model'],
        ];
    }

    private function dimensionOf(string $key): string
    {
        foreach ([
            'technical' => ['laravel', 'php_plain', 'vue_inertia', 'livewire', 'stack_db', 'api_ai', 'unconfirmed_tech'],
            'commercial' => ['freelance_contract', 'accepts_external', 'agency_type', 'consultancy_type', 'product_type', 'recruiter', 'large_outsourcer', 'multi_vacancies', 'fixed_job', 'low_prices'],
            'recurrent' => ['staff_augmentation', 'maintenance_sla', 'long_term', 'many_cases', 'long_clients', 'active_vacancy', 'one_off'],
            'vitality' => ['recent_content', 'sitemap_fresh', 'vacancy_vitality', 'copyright_recent', 'team_5_50', 'team_51_200', 'team_over_200', 'team_2_4', 'team_unknown', 'stale_content', 'old_sitemap', 'old_copyright', 'dead_web', 'solo_freelancer'],
            'communication' => ['lang_es_pt', 'async_english', 'english_unknown', 'english_fluent_required'],
            'geo_contract' => ['country_pt_es', 'country_eu', 'country_uk_ie', 'country_us_ca', 'country_other', 'accepts_eu_contractors', 'overlap_ok', 'overlap_low', 'local_contract_required'],
            'remote' => ['remote', 'hybrid', 'onsite', 'remote_unknown'],
        ] as $dimension => $keys) {
            if (in_array($key, $keys, true)) {
                return $dimension;
            }
        }

        return 'commercial';
    }
}
