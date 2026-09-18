<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Application\DTOs\GenerateDraftData;
use Modules\LeadScout\Domain\Enums\MessageVariant;
use Modules\LeadScout\Domain\Enums\OutreachKind;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Enums\Tier;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Exceptions\SuppressedException;
use Modules\LeadScout\Domain\Exceptions\TierNotContactableException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\OutreachRepositoryPort;
use Modules\LeadScout\Domain\Services\Article14Notice;
use Modules\LeadScout\Domain\Services\ChannelAdvisor;
use Modules\LeadScout\Domain\Services\DraftTemplate;
use Modules\LeadScout\Domain\Services\PersonalDataScrubber;
use Modules\LeadScout\Domain\Services\ProofPointMatcher;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Domain\ValueObjects\SkillTaxonomy;
use Modules\LeadScout\Infrastructure\Ai\LaravelAiDraftWriter;
use Modules\LeadScout\Infrastructure\Fetching\FetchLadder;
use Modules\LeadScout\Infrastructure\Logging\ApplicationLogger;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutProfileEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;

/**
 * Draft generation (spec US-5/US-10, T062): Tier A/B only, suppression
 * gate, catalog-validated provider/model (or selector override), proof
 * matched deterministically, opener written by the model from evidence +
 * public proof summary ONLY (never the CV, never names), claims checked
 * against confirmed skills, channel advised, art. 14 + opt-out appended.
 * Nothing is ever sent (FR-16/FR-40).
 *
 * @return array{outreach: ScoutOutreachEloquentModel, subject: string, unconfirmed_claims: list<string>, warnings: list<string>}
 */
final readonly class GenerateDraftHandler
{
    public function __construct(
        private CompanyRepositoryPort $companies,
        private OutreachRepositoryPort $outreaches,
        private SuppressionGate $gate,
        private ChannelAdvisor $advisor,
        private ProofPointMatcher $proofs,
        private PersonalDataScrubber $scrubber,
        private LaravelAiDraftWriter $writer,
        private FetchLadder $ladder,
        private ApplicationLogger $log,
    ) {}

    public function handle(string $companyUuid, GenerateDraftData $data, int $operatorId): array
    {
        $company = ScoutCompanyEloquentModel::query()
            ->with([
                'postings' => fn ($query) => $query->where('status', 'active'),
                'signals' => fn ($query) => $query->orderByDesc('confidence'),
                'contacts' => fn ($query) => $query->whereNull('anonymized_at'),
                'contactChannels' => fn ($query) => $query->where('status', 'active'),
            ])
            ->where('uuid', $companyUuid)
            ->first() ?? throw new CompanyNotFoundException($companyUuid);

        $this->assertNotSuppressed($company);

        $tier = $company->scoreResults()->where('is_current', true)->value('tier');

        if ($tier !== Tier::A && $tier !== Tier::B) {
            throw new TierNotContactableException;
        }

        $profile = ScoutProfileEloquentModel::query()
            ->where('user_id', $operatorId)
            ->where('is_current', true)
            ->first();

        $confirmed = $profile?->confirmed_skills ?? [];
        $proofPoints = $profile?->proof_points ?? [];

        $variant = $data->variant ?? $this->defaultVariant($company);
        $language = $data->language ?? $this->defaultLanguage($company);

        $proof = $this->proofs->match($proofPoints, $this->companyTechs($company), $company->sectors[0] ?? null);

        $evidence = $company->signals
            ->take(5)
            ->map(static fn ($signal): string => trim((string) $signal->value_text.' — '.($signal->evidence_excerpt ?? '')))
            ->implode("\n");

        $names = $company->contacts->map(static fn ($contact): string => (string) $contact->full_name)->all();

        $prompt = "Language: {$language}. Variant: {$variant}.\n\n"
            ."--- COMPANY EVIDENCE ---\n".$this->scrubber->scrub($evidence, $names)."\n\n"
            .'--- PUBLIC PROOF SUMMARY (the only capability reference allowed) ---'."\n"
            .($proof === null ? '(no citable proof yet)' : $this->scrubber->scrub("{$proof['title']}: {$proof['summary']}"))."\n";

        $written = $this->writer->write($prompt, $data->provider, $data->model);

        $unconfirmed = $this->unconfirmedClaims($written['opener'], $confirmed);

        $advice = $this->channelAdvice($company);
        $recommended = $this->recommendedChannel($company, $advice);

        $decisor = $company->contacts->firstWhere('is_primary', true) ?? $company->contacts->first();
        $firstName = $decisor === null ? '' : (string) strtok(trim((string) $decisor->full_name), ' ');

        $warnings = $this->reverifyDecisor($company, $decisor);

        $art14 = Article14Notice::for(
            $language,
            $company->name,
            'https://'.$company->canonical_domain,
            (string) config('lead-scout.privacy.contact_email'),
            (string) config('lead-scout.privacy.policy_url'),
        );

        $rendered = DraftTemplate::render(
            $variant,
            $language,
            $firstName,
            $written['opener'],
            $proof === null ? null : [
                'title' => (string) $proof['title'],
                'summary' => (string) $proof['summary'],
                'url' => $proof['url'],
            ],
            $company->name,
            $art14,
            (string) config('lead-scout.privacy.contact_email'),
        );

        $outreach = $this->outreaches->create([
            'company_id' => $company->id,
            'contact_id' => $decisor?->id,
            'contact_channel_id' => $recommended?->id,
            'operator_id' => $operatorId,
            'outreach_kind' => $advice['employment_application'] ? OutreachKind::EmploymentApplication->value : OutreachKind::ContractorOffer->value,
            'send_medium' => null,
            'sender_kind' => (string) config('lead-scout.sender_kind_default', 'personal_mailbox'),
            'stage' => OutreachStage::Draft->value,
            'draft_body' => $rendered['body'],
            'variant' => MessageVariant::from($variant)->value,
            'signal_used' => $this->signalUsed($company, $variant, $data->jobPostingId),
            'template_key' => $variant,
            'template_version' => DraftTemplate::version($variant),
            'ai_provider' => $written['provider'],
            'ai_model' => $written['model'],
            'channel_warning' => $this->channelWarning($advice, $recommended?->uuid),
            'legal_rule_status' => $this->legalRuleStatus($company, $recommended),
        ]);

        // Draft bodies never reach logs (commercial text discipline).
        $this->log->pipeline('draft_generated', [
            'company' => $company->uuid,
            'variant' => $variant,
            'provider' => $written['provider'],
            'model' => $written['model'],
            'unconfirmed_claims' => count($unconfirmed),
        ]);

        return ['outreach' => $outreach, 'subject' => $rendered['subject'], 'unconfirmed_claims' => $unconfirmed, 'warnings' => $warnings];
    }

    /**
     * Accuracy before drafting (spec FR-28): decisor data older than 90
     * days is re-fetched from its evidence page. Gone from the source →
     * anonymized with a warning; unreachable source → warning, human call.
     *
     * @return list<string>
     */
    private function reverifyDecisor(ScoutCompanyEloquentModel $company, ?object $decisor): array
    {
        if ($decisor === null || $decisor->last_verified_at === null) {
            return [];
        }

        if (CarbonImmutable::parse($decisor->last_verified_at)->gt(CarbonImmutable::now()->subDays(90))) {
            return [];
        }

        if ($decisor->evidence_url === null) {
            return ['Decisor data is older than 90 days and has no evidence page to re-verify: confirm manually.'];
        }

        $result = $this->ladder->fetch($company, $decisor->evidence_url);

        if (! $result->succeeded()) {
            return ['Could not re-verify the decisor page: confirm the person manually before sending.'];
        }

        if (! str_contains(mb_strtolower((string) $result->markdown), mb_strtolower(trim((string) $decisor->full_name)))) {
            $decisor->update([
                'full_name' => null,
                'published_email' => null,
                'public_profile_url' => null,
                'evidence_excerpt' => null,
                'anonymized_at' => now(),
            ]);

            return ['The decisor no longer appears on the evidence page and was anonymized.'];
        }

        $decisor->update(['last_verified_at' => now()]);

        return [];
    }

    private function assertNotSuppressed(ScoutCompanyEloquentModel $company): void
    {
        $candidates = $this->companies->suppressionsMatching($company->canonical_domain, $company->tax_id, $company->name)
            ->map(static fn ($row): array => [
                'canonical_domain' => $row->canonical_domain,
                'tax_id' => $row->tax_id,
                'name' => $row->name,
            ])
            ->all();

        if ($this->gate->isSuppressed($company->canonical_domain, $company->tax_id, $company->name, $candidates)) {
            throw new SuppressedException;
        }
    }

    private function defaultVariant(ScoutCompanyEloquentModel $company): string
    {
        $keys = $company->signals->map(static fn ($signal): string => $signal->signal_key)->all();

        if ($company->postings->isNotEmpty() || in_array('active_vacancy', $keys, true)) {
            return MessageVariant::Vacancy->value;
        }

        if (count(array_intersect($keys, ['laravel', 'vue_inertia', 'livewire', 'stack_db'])) > 0) {
            return MessageVariant::Stack->value;
        }

        if (in_array('maintenance_sla', $keys, true) || in_array('long_term', $keys, true)) {
            return MessageVariant::Legacy->value;
        }

        if (($company->sectors ?? []) !== []) {
            return MessageVariant::Sector->value;
        }

        return MessageVariant::Stack->value;
    }

    private function defaultLanguage(ScoutCompanyEloquentModel $company): string
    {
        return match ($company->country) {
            'PT' => 'pt',
            'ES' => 'es',
            default => in_array($company->country, ['BR', 'MX', 'AR', 'UY', 'CL', 'CO'], true) ? 'es' : 'en',
        };
    }

    /**
     * @return list<string>
     */
    private function companyTechs(ScoutCompanyEloquentModel $company): array
    {
        $techs = [];

        foreach ($company->signals as $signal) {
            if ($signal->dimension->value === 'technical' && $signal->value_text !== null) {
                $techs[] = $signal->value_text;
            }
        }

        return $techs;
    }

    /**
     * @return list<string>
     */
    private function unconfirmedClaims(string $opener, array $confirmed): array
    {
        $confirmed = array_map(strtolower(...), $confirmed);
        $watchList = (array) config('lead-scout.skills.watch_list', []);
        $claims = [];

        foreach (SkillTaxonomy::claimTerms($watchList) as $term) {
            if (SkillTaxonomy::mentions($opener, $term) && ! in_array($term, $confirmed, true)) {
                $claims[] = $term;
            }
        }

        return array_values(array_unique($claims));
    }

    /**
     * @return array{ranked: list<array{uuid: ?string, type: string, url: ?string, rank: int, allowed: bool, blocked_reason: ?string, warning: ?string}>, recommended_uuid: ?string, cold_email_allowed: bool, employment_application: bool}
     */
    private function channelAdvice(ScoutCompanyEloquentModel $company): array
    {
        $primary = $company->contacts->firstWhere('is_primary', true) ?? $company->contacts->first();

        return $this->advisor->advise(
            $company->contactChannels->map(static fn ($channel): array => [
                'uuid' => $channel->uuid,
                'type' => $channel->channel_type->value,
                'url' => $channel->url,
                'generic_email' => $channel->generic_email,
                'status' => $channel->status->value,
                'audience' => $channel->audience?->value,
            ])->all(),
            [
                'country' => $company->country,
                'has_offer' => $company->postings->isNotEmpty(),
                'is_employment_offer' => false,
                'discovery_without_offer' => $company->origin->value === 'discovery' && $company->postings->isEmpty(),
                'has_decisor' => $company->contacts->isNotEmpty(),
                'nominative_email' => $primary?->email_kind?->value === 'nominative' ? $primary->published_email : null,
                'dgc_listed' => $this->dgcListed($company),
                'dgc_list_stale' => $this->dgcListStale(),
            ],
            (array) config('lead-scout.contact_rules', []),
        );
    }

    private function recommendedChannel(ScoutCompanyEloquentModel $company, array $advice): ?object
    {
        if (($advice['recommended_uuid'] ?? null) === null) {
            return null;
        }

        return $company->contactChannels->firstWhere('uuid', $advice['recommended_uuid']);
    }

    private function channelWarning(array $advice, ?string $recommendedUuid): ?string
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

    private function legalRuleStatus(ScoutCompanyEloquentModel $company, ?object $channel): ?string
    {
        if ($channel === null) {
            return null;
        }

        $type = $channel->channel_type->value;

        if (in_array($type, ['job_posting_apply'], true)) {
            return null;
        }

        if (in_array($type, ['contact_form', 'careers_form', 'company_network_page'], true)) {
            return in_array($company->country, ['ES', 'PT'], true) ? 'pending_verification' : null;
        }

        foreach ((array) config('lead-scout.contact_rules', []) as $rule) {
            if (mb_strtoupper((string) ($rule['country'] ?? '')) === mb_strtoupper((string) $company->country)
                && ($rule['medium'] ?? null) === 'email') {
                return $rule['legal_status'];
            }
        }

        return null;
    }

    private function signalUsed(ScoutCompanyEloquentModel $company, string $variant, ?string $jobPostingId): ?string
    {
        if ($jobPostingId !== null) {
            return $jobPostingId;
        }

        $keys = $company->signals->map(static fn ($signal): string => $signal->signal_key)->all();

        return match ($variant) {
            'vacancy' => in_array('active_vacancy', $keys, true) ? 'active_vacancy' : ($keys[0] ?? null),
            'stack' => in_array('laravel', $keys, true) ? 'laravel' : ($keys[0] ?? null),
            'legacy' => in_array('maintenance_sla', $keys, true) ? 'maintenance_sla' : ($keys[0] ?? null),
            default => $keys[0] ?? null,
        };
    }

    private function dgcListed(ScoutCompanyEloquentModel $company): bool
    {
        return ScoutSuppressionEloquentModel::query()
            ->where('source', 'dgc_list')
            ->where(function ($query) use ($company): void {
                $query->where('canonical_domain', $company->canonical_domain);

                if ($company->tax_id !== null) {
                    $query->orWhere('tax_id', $company->tax_id);
                }

                $query->orWhere('name', $company->name);
            })
            ->exists();
    }

    private function dgcListStale(): bool
    {
        $latest = ScoutSuppressionEloquentModel::query()
            ->where('source', 'dgc_list')
            ->max('created_at');

        return $latest === null || $latest < now()->subMonths(3)->toDateTimeString();
    }
}
