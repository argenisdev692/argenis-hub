<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\Entities\Company;
use Modules\LeadScout\Domain\Entities\FetchedPage;
use Modules\LeadScout\Domain\Entities\JobPosting;
use Modules\LeadScout\Domain\Entities\Signal;
use Modules\LeadScout\Domain\Enums\CompanyType;
use Modules\LeadScout\Domain\Enums\ExtractionMethod;
use Modules\LeadScout\Domain\Enums\SignalDimension;
use Modules\LeadScout\Domain\Enums\SignalNature;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\FetchedPageRepositoryPort;
use Modules\LeadScout\Domain\Ports\JobPostingRepositoryPort;
use Modules\LeadScout\Domain\Ports\SignalExtractorPort;
use Modules\LeadScout\Domain\Ports\SignalRepositoryPort;
use Modules\LeadScout\Domain\Services\PersonalDataScrubber;
use Modules\LeadScout\Domain\Services\RuleBasedSignalExtractor;
use Modules\LeadScout\Domain\ValueObjects\NewSignal;
use Modules\LeadScout\Domain\ValueObjects\SignalKey;

/**
 * Two-pass signal extraction (spec FR-6/FR-10, T057): deterministic rules
 * first, then the LLM ONLY for dimensions rules cannot resolve
 * (commercial, recurrent, company type, communication gaps). Team pages
 * never reach the model and every prompt is scrubbed of PII and decisor
 * names first (FR-25); every returned excerpt is verified literally.
 */
final readonly class ExtractSignalsHandler
{
    /** Dimensions only the model can fill when rules found nothing. */
    private const array LLM_DIMENSIONS = [SignalDimension::Commercial, SignalDimension::Recurrent, SignalDimension::Communication];

    public function __construct(
        private RuleBasedSignalExtractor $rules,
        private SignalExtractorPort $ai,
        private PersonalDataScrubber $scrubber,
        private ExtractDecisionMakersHandler $decisors,
        private CompanyRepositoryPort $companies,
        private FetchedPageRepositoryPort $pages,
        private JobPostingRepositoryPort $postings,
        private SignalRepositoryPort $signals,
    ) {}

    /**
     * @return array{rules: int, ai: int, discarded: int, provider: ?string, model: ?string}
     */
    public function handle(string $companyUuid): array
    {
        $company = $this->companies->byUuid($companyUuid) ?? throw new CompanyNotFoundException($companyUuid);

        $pages = array_map(static fn (FetchedPage $page): array => [
            'url' => $page->url,
            'page_type' => $page->pageType?->value,
            'markdown' => (string) $page->contentMarkdown,
        ], $this->pages->withContent($company->id));

        $postings = array_map(static fn (JobPosting $posting): array => [
            'title' => $posting->title,
            'body' => $posting->bodyText,
            'remote_mode' => $posting->remoteMode->value,
            'contract_type' => $posting->contractType->value,
            'language' => $posting->language,
            'published_at' => $posting->publishedAt?->format('Y-m-d H:i:s'),
            'status' => $posting->status->value,
            'source_url' => $posting->sourceUrl,
        ], $this->postings->activeForCompany($company->id));

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
     * @param  list<array{url: string, page_type: ?string, markdown: string}>  $pages
     * @param  list<array{title: string, body: ?string, remote_mode: string, contract_type: string, language: ?string, published_at: ?string, status: string, source_url: string}>  $postings
     */
    private function persistRuleSignals(Company $company, array $pages, array $postings): int
    {
        $now = CarbonImmutable::now();

        $signals = $this->rules->extract(
            ['country' => $company->country, 'canonical_domain' => $company->canonicalDomain],
            array_map(static fn (array $page): array => [
                'url' => $page['url'], 'markdown' => $page['markdown'], 'fetched_at' => $now->toDateTimeString(),
            ], $pages),
            $postings,
        );

        $kept = 0;

        foreach ($signals as $signal) {
            $stored = $this->signals->addIfAbsent($company->id, new NewSignal(
                dimension: SignalDimension::from($signal['dimension']),
                signalKey: $signal['signal_key'],
                nature: SignalNature::from($signal['nature']),
                confidence: $signal['confidence'],
                extractionMethod: ExtractionMethod::Rule,
                capturedAt: CarbonImmutable::parse($signal['captured_at']),
                valueText: $signal['value_text'],
                evidenceUrl: $signal['evidence_url'],
                evidenceExcerpt: $signal['evidence_excerpt'],
            ));

            if ($stored) {
                $kept++;
            }
        }

        return $kept;
    }

    private function needsAi(Company $company): bool
    {
        $dimensions = array_map(
            static fn (Signal $signal): SignalDimension => $signal->dimension,
            $this->signals->forCompany($company->id),
        );

        foreach (self::LLM_DIMENSIONS as $dimension) {
            if (! in_array($dimension, $dimensions, true)) {
                return true;
            }
        }

        return $company->companyType === null;
    }

    /**
     * @param  list<array{url: string, page_type: ?string, markdown: string}>  $pages
     * @return array{kept: int, discarded: int, provider: ?string, model: ?string}
     */
    private function runAi(Company $company, array $pages): array
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
        $now = CarbonImmutable::now();
        $kept = 0;

        foreach ($result['signals'] as $signal) {
            $stored = $this->signals->addIfAbsent($company->id, new NewSignal(
                dimension: SignalKey::dimensionOf($signal['signal_key']),
                signalKey: $signal['signal_key'],
                nature: SignalNature::from($signal['nature']),
                confidence: $signal['confidence'],
                extractionMethod: ExtractionMethod::Ai,
                capturedAt: $now,
                evidenceUrl: $signal['source_url'] !== '' ? $signal['source_url'] : null,
                evidenceExcerpt: $signal['excerpt'],
                aiProvider: $result['provider'],
                aiModel: $result['model'],
            ));

            if ($stored) {
                $kept++;
            }
        }

        $this->companies->recordClassification(
            $company,
            $company->companyType === null && $result['company_type'] !== null ? CompanyType::tryFrom($result['company_type']) : null,
            $company->teamSizeObserved === null ? $result['team_size_observed'] : null,
        );

        return [
            'kept' => $kept,
            'discarded' => $result['discarded'],
            'provider' => $result['provider'],
            'model' => $result['model'],
        ];
    }
}
