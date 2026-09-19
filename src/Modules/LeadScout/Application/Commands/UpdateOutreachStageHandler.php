<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\LeadScout\Application\DTOs\UpdateOutreachData;
use Modules\LeadScout\Domain\Entities\ContactChannel;
use Modules\LeadScout\Domain\Entities\Outreach;
use Modules\LeadScout\Domain\Enums\ChannelStatus;
use Modules\LeadScout\Domain\Enums\LegalRuleStatus;
use Modules\LeadScout\Domain\Enums\OutreachChannel;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Exceptions\InvalidInputException;
use Modules\LeadScout\Domain\Exceptions\SuppressedException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\ContactChannelRepositoryPort;
use Modules\LeadScout\Domain\Ports\ContactRepositoryPort;
use Modules\LeadScout\Domain\Ports\OutreachRepositoryPort;
use Modules\LeadScout\Domain\Ports\ProfileRepositoryPort;
use Modules\LeadScout\Domain\Ports\SuppressionRepositoryPort;
use Modules\LeadScout\Domain\Ports\TransactionPort;
use Modules\LeadScout\Domain\Services\ChannelAdvisor;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Domain\ValueObjects\SkillTaxonomy;

/**
 * Outreach stage machine (spec US-5/US-6, FR-41, T063): explicit valid
 * transitions, claim-clean `ready`, fully-evidenced manual `sent`.
 * `operator_id` always comes from the session — client input is ignored.
 *
 * Everything is validated before the first write, and the writes land in
 * one transaction: a rejected send leaves no trace.
 */
final readonly class UpdateOutreachStageHandler
{
    private const int DAILY_SEND_WARNING = 15;

    public function __construct(
        private OutreachRepositoryPort $outreaches,
        private CompanyRepositoryPort $companies,
        private ContactChannelRepositoryPort $channels,
        private ContactRepositoryPort $contacts,
        private ProfileRepositoryPort $profiles,
        private SuppressionGate $gate,
        private ChannelAdvisor $advisor,
        private SuppressionRepositoryPort $suppressions,
        private TransactionPort $transaction,
        private Config $config,
    ) {}

    /**
     * @return array{outreach: Outreach, daily_sent: int, daily_limit_warning: bool}
     */
    public function handle(string $outreachUuid, UpdateOutreachData $data, int $operatorId): array
    {
        $outreach = $this->outreaches->byUuid($outreachUuid)
            ?? throw InvalidInputException::withMessages(['outreach' => 'Outreach not found.']);

        $to = $data->stage === null ? $outreach->stage : OutreachStage::from($data->stage);

        if (! $outreach->stage->canMoveTo($to)) {
            throw InvalidInputException::withMessages([
                'stage' => "Transition {$outreach->stage->value} → {$to->value} is not allowed.",
            ]);
        }

        if ($to === OutreachStage::Ready) {
            $this->assertReadyBody($data->draftBody ?? (string) $outreach->draftBody, $operatorId);
        }

        $send = $to === OutreachStage::Sent ? $this->validatedSend($outreach, $data) : null;
        $now = CarbonImmutable::now();

        $moved = $this->transaction->run(function () use ($outreach, $data, $send, $to, $operatorId, $now): Outreach {
            $outreach = $this->outreaches->annotate(
                $outreach,
                $data->draftBody === null ? null : mb_substr($data->draftBody, 0, 10000),
                $data->notes === null ? null : mb_substr(trim($data->notes), 0, 2000),
            );

            if ($send !== null) {
                $outreach = $this->outreaches->recordSend(
                    $outreach,
                    $send['channel']->id,
                    $send['medium'],
                    $data->senderKind ?? (string) $this->config->get('lead-scout.sender_kind_default', 'personal_mailbox'),
                    $send['legal'],
                    $send['legal'] === LegalRuleStatus::PendingVerification ? $now : null,
                    $now,
                );
                $this->channels->setStatus($send['channel'], ChannelStatus::Used);

                if ($outreach->contactId !== null) {
                    $this->contacts->markNotified($outreach->contactId, $now);
                }
            }

            return $this->outreaches->moveToStage($outreach, $to, $operatorId);
        });

        $dailySent = $this->outreaches->countSentOn($operatorId, $now);

        return [
            'outreach' => $moved,
            'daily_sent' => $dailySent,
            'daily_limit_warning' => $dailySent > self::DAILY_SEND_WARNING,
        ];
    }

    /**
     * Ready means reviewed: body present and no unconfirmed capability
     * claims (spec US-5 CA-5).
     */
    private function assertReadyBody(string $body, int $operatorId): void
    {
        if (trim($body) === '') {
            throw InvalidInputException::withMessages(['draft_body' => 'A draft body is required before marking ready.']);
        }

        // The art. 14 notice + opt-out line are template-inserted and never
        // removable: a hand-edited body without them cannot go ready (FR-26).
        $hasNotice = str_contains($body, 'Aviso de privacidad')
            || str_contains($body, 'Privacy notice')
            || str_contains($body, 'Aviso de privacidade');

        if (! $hasNotice || ! str_contains($body, 'BAJA')) {
            throw InvalidInputException::withMessages([
                'draft_body' => 'The privacy notice and opt-out line are mandatory and cannot be removed.',
            ]);
        }

        $unconfirmed = SkillTaxonomy::unconfirmedClaims(
            $body,
            $this->profiles->current($operatorId)?->confirmedSkills ?? [],
            (array) $this->config->get('lead-scout.skills.watch_list', []),
        );

        if ($unconfirmed !== []) {
            throw InvalidInputException::withMessages([
                'draft_body' => 'Unconfirmed capabilities: '.implode(', ', $unconfirmed).'. Confirm them in the profile first.',
            ]);
        }
    }

    /**
     * Manual `sent` registration (spec FR-41): channel + medium + mailbox
     * kind + legal state, all evidenced. The module never sends (FR-16).
     *
     * @return array{channel: ContactChannel, medium: OutreachChannel, legal: ?LegalRuleStatus}
     */
    private function validatedSend(Outreach $outreach, UpdateOutreachData $data): array
    {
        $company = $this->companies->byId($outreach->companyId)
            ?? throw InvalidInputException::withMessages(['outreach' => 'Outreach not found.']);

        $candidates = $this->suppressions->matching($company->canonicalDomain, $company->taxId, $company->name);

        if ($this->gate->isSuppressed($company->canonicalDomain, $company->taxId, $company->name, $candidates)) {
            throw new SuppressedException;
        }

        if ($data->contactChannelId === null) {
            throw InvalidInputException::withMessages(['contact_channel_id' => 'Sending requires the used channel.']);
        }

        $channel = $this->channels->byUuid($data->contactChannelId);

        if ($channel === null || $channel->companyId !== $company->id || $channel->status !== ChannelStatus::Active) {
            throw InvalidInputException::withMessages(['contact_channel_id' => 'Channel must belong to the company and be active.']);
        }

        if ($data->sendMedium === null) {
            throw InvalidInputException::withMessages(['send_medium' => 'Sending requires the send medium.']);
        }

        $legal = $this->advisor->legalRuleStatus(
            $channel->channelType,
            $company->country,
            (array) $this->config->get('lead-scout.contact_rules', []),
        );

        if ($legal === LegalRuleStatus::PendingVerification && ($data->acknowledgePendingLegal ?? false) !== true) {
            throw InvalidInputException::withMessages([
                'acknowledge_pending_legal' => 'This channel rule is pending legal verification: confirm explicitly to record the send.',
            ]);
        }

        return ['channel' => $channel, 'medium' => OutreachChannel::from($data->sendMedium), 'legal' => $legal];
    }
}
