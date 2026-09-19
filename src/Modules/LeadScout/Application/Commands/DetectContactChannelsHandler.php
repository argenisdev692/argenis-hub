<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\Entities\FetchedPage;
use Modules\LeadScout\Domain\Enums\ChannelAudience;
use Modules\LeadScout\Domain\Enums\ChannelType;
use Modules\LeadScout\Domain\Enums\ExtractionMethod;
use Modules\LeadScout\Domain\Enums\SignalDimension;
use Modules\LeadScout\Domain\Enums\SignalNature;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\ContactChannelRepositoryPort;
use Modules\LeadScout\Domain\Ports\FetchedPageRepositoryPort;
use Modules\LeadScout\Domain\Ports\JobPostingRepositoryPort;
use Modules\LeadScout\Domain\Ports\SignalRepositoryPort;
use Modules\LeadScout\Domain\Services\ContactChannelDetector;
use Modules\LeadScout\Domain\ValueObjects\NewSignal;

/**
 * Detects and upserts contact channels from stored pages + the offer
 * (spec US-12, FR-31, T052). Pure reads, only writes: no form is ever
 * filled, sent or invoked, no CAPTCHA touched, no phones stored (FR-32).
 * Invitation wording also persists as a verified commercial signal.
 */
final readonly class DetectContactChannelsHandler
{
    public function __construct(
        private ContactChannelDetector $detector,
        private CompanyRepositoryPort $companies,
        private FetchedPageRepositoryPort $pages,
        private JobPostingRepositoryPort $postings,
        private ContactChannelRepositoryPort $channels,
        private SignalRepositoryPort $signals,
    ) {}

    /**
     * @return array{channels: int, signals: int}
     */
    public function handle(string $companyUuid): array
    {
        $company = $this->companies->byUuid($companyUuid) ?? throw new CompanyNotFoundException($companyUuid);

        $pages = array_map(static fn (FetchedPage $page): array => [
            'url' => $page->url,
            'markdown' => (string) $page->contentMarkdown,
            'forms_summary' => $page->formsSummary,
        ], $this->pages->withContent($company->id));

        $offer = array_first($this->postings->activeForCompany($company->id));

        $detected = $this->detector->detect(
            $pages,
            $company->canonicalDomain,
            $offer === null ? null : ['url' => $offer->sourceUrl, 'apply_email' => null],
        );

        $channels = 0;

        foreach ($detected['channels'] as $channel) {
            $type = ChannelType::from($channel['type']);
            $isForm = $type === ChannelType::ContactForm || $type === ChannelType::CareersForm;

            $created = $this->channels->upsertDetected(
                companyId: $company->id,
                type: $type,
                url: $channel['url'],
                genericEmail: $channel['generic_email'],
                formFields: $isForm ? self::formFields($pages, (string) $channel['url']) : null,
                hasCaptcha: (bool) $channel['has_captcha'],
                audience: $channel['audience'] === null ? null : ChannelAudience::from($channel['audience']),
                evidenceUrl: $channel['evidence_url'],
                evidenceExcerpt: $channel['excerpt'],
            );

            if ($created) {
                $channels++;
            }
        }

        $signals = 0;

        foreach ($detected['impliedSignals'] as $implied) {
            $stored = $this->signals->addIfAbsent($company->id, new NewSignal(
                dimension: SignalDimension::Commercial,
                signalKey: $implied['signal_key'],
                nature: SignalNature::Fact,
                confidence: 80,
                extractionMethod: ExtractionMethod::Rule,
                capturedAt: CarbonImmutable::now(),
                evidenceUrl: $implied['url'],
                evidenceExcerpt: $implied['excerpt'],
            ));

            if ($stored) {
                $signals++;
            }
        }

        return ['channels' => $channels, 'signals' => $signals];
    }

    /**
     * @param  list<array{url: string, markdown: string, forms_summary: array<string, mixed>|null}>  $pages
     * @return list<string>|null
     */
    private static function formFields(array $pages, string $url): ?array
    {
        foreach ($pages as $page) {
            if ($page['url'] === $url && is_array($page['forms_summary'])) {
                $first = $page['forms_summary']['forms'][0] ?? null;

                return is_array($first) ? array_values((array) ($first['fields'] ?? [])) : null;
            }
        }

        return null;
    }
}
