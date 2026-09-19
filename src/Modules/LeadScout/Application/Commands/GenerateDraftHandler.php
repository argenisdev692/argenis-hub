<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\LeadScout\Application\DTOs\GenerateDraftData;
use Modules\LeadScout\Application\Queries\AdviseContactChannelsHandler;
use Modules\LeadScout\Application\Queries\GetDraftContextHandler;
use Modules\LeadScout\Domain\Entities\Contact;
use Modules\LeadScout\Domain\Entities\ContactChannel;
use Modules\LeadScout\Domain\Entities\Outreach;
use Modules\LeadScout\Domain\Enums\MessageVariant;
use Modules\LeadScout\Domain\Enums\OutreachKind;
use Modules\LeadScout\Domain\Ports\OutreachRepositoryPort;
use Modules\LeadScout\Domain\Ports\PipelineLoggerPort;
use Modules\LeadScout\Domain\Services\Article14Notice;
use Modules\LeadScout\Domain\Services\DraftPlanner;
use Modules\LeadScout\Domain\Services\DraftTemplate;
use Modules\LeadScout\Domain\ValueObjects\NewOutreachDraft;

/**
 * Draft generation (spec US-5/US-10, T062). Orchestrates, in order:
 * contactable-lead context (Tier A/B, not suppressed) → AI opener grounded
 * on evidence + public proof → channel advice → decisor re-verification →
 * template with art. 14 notice + opt-out → stored `draft` outreach.
 * Nothing is ever sent (FR-16/FR-40).
 */
final readonly class GenerateDraftHandler
{
    public function __construct(
        private GetDraftContextHandler $contexts,
        private ComposeDraftOpenerHandler $opener,
        private AdviseContactChannelsHandler $channels,
        private ReverifyDecisorHandler $reverify,
        private DraftPlanner $planner,
        private OutreachRepositoryPort $outreaches,
        private PipelineLoggerPort $log,
        private Config $config,
    ) {}

    /**
     * @return array{outreach: Outreach, subject: string, unconfirmed_claims: list<string>, warnings: list<string>}
     */
    public function handle(string $companyUuid, GenerateDraftData $data, int $operatorId): array
    {
        $context = $this->contexts->handle($companyUuid, $operatorId);
        $company = $context->company;

        $variant = $data->variant ?? $this->planner->variant($context)->value;
        $language = $data->language ?? $this->planner->language($company->country);

        $written = $this->opener->handle($context, $language, $variant, $data->provider, $data->model);

        $advice = $this->channels->handle($company, $context->channels, $context->contacts, $context->hasOffer, false);
        $recommended = self::recommendedChannel($context->channels, $advice['recommended_uuid']);

        $decisor = Contact::primaryOf($context->contacts);
        $firstName = $decisor === null ? '' : (string) strtok(trim((string) $decisor->fullName), ' ');
        $warnings = $this->reverify->handle($decisor);

        $contactEmail = (string) $this->config->get('lead-scout.privacy.contact_email');

        $rendered = DraftTemplate::render(
            $variant,
            $language,
            $firstName,
            $written['opener'],
            $written['proof'],
            $company->name,
            Article14Notice::for(
                $language,
                $company->name,
                'https://'.$company->canonicalDomain,
                $contactEmail,
                (string) $this->config->get('lead-scout.privacy.policy_url'),
            ),
            $contactEmail,
        );

        $outreach = $this->outreaches->createDraft(new NewOutreachDraft(
            companyId: $company->id,
            contactId: $decisor?->id,
            contactChannelId: $recommended?->id,
            operatorId: $operatorId,
            outreachKind: $advice['employment_application'] ? OutreachKind::EmploymentApplication : OutreachKind::ContractorOffer,
            senderKind: (string) $this->config->get('lead-scout.sender_kind_default', 'personal_mailbox'),
            draftBody: $rendered['body'],
            variant: MessageVariant::from($variant),
            signalUsed: $this->planner->signalUsed($context, $variant, $data->jobPostingId),
            templateKey: $variant,
            templateVersion: (int) $this->config->get("lead-scout.draft_templates.{$variant}.version", 1),
            aiProvider: $written['provider'],
            aiModel: $written['model'],
            channelWarning: $this->planner->channelWarning($advice, $recommended?->uuid),
            legalRuleStatus: $recommended === null ? null : $this->channels->legalRuleStatus($company, $recommended),
        ));

        // Draft bodies never reach logs (commercial text discipline).
        $this->log->pipeline('draft_generated', [
            'company' => $company->uuid,
            'variant' => $variant,
            'provider' => $written['provider'],
            'model' => $written['model'],
            'unconfirmed_claims' => count($written['unconfirmed_claims']),
        ]);

        return [
            'outreach' => $outreach,
            'subject' => $rendered['subject'],
            'unconfirmed_claims' => $written['unconfirmed_claims'],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  list<ContactChannel>  $channels
     */
    private static function recommendedChannel(array $channels, ?string $uuid): ?ContactChannel
    {
        return $uuid === null
            ? null
            : array_find($channels, static fn (ContactChannel $channel): bool => $channel->uuid === $uuid);
    }
}
