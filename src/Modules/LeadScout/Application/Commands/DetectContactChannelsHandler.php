<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Services\ContactChannelDetector;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactChannelEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSignalEloquentModel;

/**
 * Detects and upserts contact channels from stored pages + the offer
 * (spec US-12, FR-31, T052). Pure reads, only writes: no form is ever
 * filled, sent or invoked, no CAPTCHA touched, no phones stored (FR-32).
 * Invitation wording also persists as a verified commercial signal.
 */
final readonly class DetectContactChannelsHandler
{
    public function __construct(private ContactChannelDetector $detector) {}

    /**
     * @return array{channels: int, signals: int}
     */
    public function handle(string $companyUuid): array
    {
        $company = ScoutCompanyEloquentModel::query()->where('uuid', $companyUuid)->first()
            ?? throw new CompanyNotFoundException($companyUuid);

        $pages = $company->fetchedPages()
            ->whereNotNull('content_markdown')
            ->orderBy('id')
            ->get(['url', 'content_markdown', 'forms_summary'])
            ->map(static fn ($page): array => [
                'url' => $page->url,
                'markdown' => (string) $page->content_markdown,
                'forms_summary' => $page->forms_summary,
            ])
            ->all();

        $offer = $company->postings()->where('status', 'active')->orderByDesc('published_at')->first(['source_url']);

        $detected = $this->detector->detect(
            $pages,
            $company->canonical_domain,
            $offer === null ? null : ['url' => $offer->source_url, 'apply_email' => null],
        );

        $channels = 0;

        foreach ($detected['channels'] as $channel) {
            $existing = ScoutContactChannelEloquentModel::query()
                ->where('company_id', $company->id)
                ->where('channel_type', $channel['type'])
                ->where('url', $channel['url'])
                ->first();

            if ($existing === null) {
                ScoutContactChannelEloquentModel::query()->create([
                    'company_id' => $company->id,
                    'channel_type' => $channel['type'],
                    'url' => $channel['url'],
                    'generic_email' => $channel['generic_email'],
                    'form_fields' => $channel['type'] === 'contact_form' || $channel['type'] === 'careers_form'
                        ? $this->formFields($pages, (string) $channel['url'])
                        : null,
                    'has_captcha' => $channel['has_captcha'],
                    'audience' => $channel['audience'],
                    'evidence_url' => $channel['evidence_url'],
                    'evidence_excerpt' => $channel['excerpt'],
                ]);
                $channels++;
            } else {
                $existing->update([
                    'generic_email' => $channel['generic_email'] ?? $existing->generic_email,
                    'evidence_url' => $channel['evidence_url'],
                    'evidence_excerpt' => $channel['excerpt'],
                ]);
            }
        }

        $signals = 0;

        foreach ($detected['impliedSignals'] as $implied) {
            $exists = ScoutSignalEloquentModel::query()
                ->where('company_id', $company->id)
                ->where('signal_key', $implied['signal_key'])
                ->exists();

            if ($exists) {
                continue;
            }

            ScoutSignalEloquentModel::query()->create([
                'company_id' => $company->id,
                'dimension' => 'commercial',
                'signal_key' => $implied['signal_key'],
                'nature' => 'fact',
                'confidence' => 80,
                'evidence_url' => $implied['url'],
                'evidence_excerpt' => $implied['excerpt'],
                'captured_at' => now(),
                'extraction_method' => 'rule',
            ]);
            $signals++;
        }

        return ['channels' => $channels, 'signals' => $signals];
    }

    /**
     * @return list<string>|null
     */
    private function formFields(array $pages, string $url): ?array
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
