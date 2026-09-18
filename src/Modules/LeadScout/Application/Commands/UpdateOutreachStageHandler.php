<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Validation\ValidationException;
use Modules\LeadScout\Application\DTOs\UpdateOutreachData;
use Modules\LeadScout\Domain\Enums\LegalRuleStatus;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Exceptions\SuppressedException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\OutreachRepositoryPort;
use Modules\LeadScout\Domain\Services\ChannelAdvisor;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Domain\ValueObjects\SkillTaxonomy;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactChannelEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutProfileEloquentModel;

/**
 * Outreach stage machine (spec US-5/US-6, FR-41, T063): explicit valid
 * transitions, claim-clean `ready`, fully-evidenced manual `sent`.
 * `operator_id` always comes from the session — client input is ignored.
 *
 * @return array{outreach: ScoutOutreachEloquentModel, daily_sent: int, daily_limit_warning: bool}
 */
final readonly class UpdateOutreachStageHandler
{
    /**
     * @var array<string, list<string>>
     */
    private const array TRANSITIONS = [
        'draft' => ['draft', 'ready'],
        'ready' => ['draft', 'ready', 'sent'],
        'sent' => ['sent', 'call', 'lost'],
        'replied' => ['positive', 'lost'],
        'positive' => ['call', 'lost'],
        'call' => ['trial', 'lost'],
        'trial' => ['won', 'lost'],
        'won' => ['recurrent', 'lost'],
        'recurrent' => ['recurrent'],
        'lost' => [],
        'do_not_contact' => [],
    ];

    public function __construct(
        private OutreachRepositoryPort $outreaches,
        private CompanyRepositoryPort $companies,
        private SuppressionGate $gate,
        private ChannelAdvisor $advisor,
    ) {}

    public function handle(string $outreachUuid, UpdateOutreachData $data, int $operatorId): array
    {
        $outreach = $this->outreaches->findByUuid($outreachUuid);

        if ($outreach === null) {
            throw ValidationException::withMessages(['outreach' => 'Outreach not found.']);
        }

        $outreach->loadMissing(['company', 'contact', 'contactChannel']);

        $to = $data->stage === null ? $outreach->stage : OutreachStage::from($data->stage);

        if (! in_array($to->value, self::TRANSITIONS[$outreach->stage->value] ?? [], true)) {
            throw ValidationException::withMessages([
                'stage' => "Transition {$outreach->stage->value} → {$to->value} is not allowed.",
            ]);
        }

        // Validate against the effective body BEFORE persisting: a rejected
        // `ready` must not leave the invalid text saved.
        $effectiveBody = $data->draftBody ?? (string) $outreach->draft_body;

        if ($to === OutreachStage::Ready) {
            $this->assertReadyBody($effectiveBody, $operatorId);
        }

        if ($to === OutreachStage::Sent) {
            $this->assertSent($outreach, $data, $operatorId);
        }

        if ($data->draftBody !== null) {
            $outreach->update(['draft_body' => mb_substr($data->draftBody, 0, 10000)]);
        }

        if ($data->notes !== null) {
            $outreach->update(['notes' => mb_substr(trim($data->notes), 0, 2000)]);
        }

        $moved = $this->outreaches->moveToStage($outreach->refresh(), $to, $operatorId);
        $moved->loadMissing('company');

        $dailySent = ScoutOutreachEloquentModel::query()
            ->where('operator_id', $operatorId)
            ->where('stage', OutreachStage::Sent->value)
            ->whereDate('sent_at', today())
            ->count();

        return [
            'outreach' => $moved,
            'daily_sent' => $dailySent,
            'daily_limit_warning' => $dailySent > 15,
        ];
    }

    /**
     * Ready means reviewed: body present and no unconfirmed capability
     * claims (spec US-5 CA-5).
     */
    private function assertReadyBody(string $body, int $operatorId): void
    {
        if (trim($body) === '') {
            throw ValidationException::withMessages(['draft_body' => 'A draft body is required before marking ready.']);
        }

        // The art. 14 notice + opt-out line are template-inserted and never
        // removable: a hand-edited body without them cannot go ready (FR-26).
        $hasNotice = str_contains($body, 'Aviso de privacidad')
            || str_contains($body, 'Privacy notice')
            || str_contains($body, 'Aviso de privacidade');

        if (! $hasNotice || ! str_contains($body, 'BAJA')) {
            throw ValidationException::withMessages([
                'draft_body' => 'The privacy notice and opt-out line are mandatory and cannot be removed.',
            ]);
        }

        $confirmed = ScoutProfileEloquentModel::query()
            ->where('user_id', $operatorId)
            ->where('is_current', true)
            ->value('confirmed_skills') ?? [];

        $unconfirmed = [];
        $watchList = (array) config('lead-scout.skills.watch_list', []);

        foreach (SkillTaxonomy::claimTerms($watchList) as $term) {
            if (SkillTaxonomy::mentions($body, $term)
                && ! in_array($term, array_map(strtolower(...), (array) $confirmed), true)) {
                $unconfirmed[] = $term;
            }
        }

        if ($unconfirmed !== []) {
            throw ValidationException::withMessages([
                'draft_body' => 'Unconfirmed capabilities: '.implode(', ', $unconfirmed).'. Confirm them in the profile first.',
            ]);
        }
    }

    /**
     * Manual `sent` registration (spec FR-41): channel + medium + mailbox
     * kind + legal state, all evidenced. The module never sends (FR-16).
     */
    private function assertSent(
        ScoutOutreachEloquentModel $outreach,
        UpdateOutreachData $data,
        int $operatorId,
    ): void {
        $company = $outreach->company;

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

        if ($data->contactChannelId === null) {
            throw ValidationException::withMessages(['contact_channel_id' => 'Sending requires the used channel.']);
        }

        $channel = ScoutContactChannelEloquentModel::query()
            ->where('uuid', $data->contactChannelId)
            ->where('company_id', $company->id)
            ->first();

        if ($channel === null || $channel->status->value !== 'active') {
            throw ValidationException::withMessages(['contact_channel_id' => 'Channel must belong to the company and be active.']);
        }

        if ($data->sendMedium === null) {
            throw ValidationException::withMessages(['send_medium' => 'Sending requires the send medium.']);
        }

        $outreach->update([
            'contact_channel_id' => $channel->id,
            'send_medium' => $data->sendMedium,
            'sender_kind' => $data->senderKind ?? (string) config('lead-scout.sender_kind_default', 'personal_mailbox'),
            'sent_at' => now(),
            'stage_changed_at' => now(),
        ]);

        $channel->update(['status' => 'used']);

        $legal = $this->legalStatus($company, $channel);

        if ($legal === LegalRuleStatus::PendingVerification->value && ($data->acknowledgePendingLegal ?? false) !== true) {
            throw ValidationException::withMessages([
                'acknowledge_pending_legal' => 'This channel rule is pending legal verification: confirm explicitly to record the send.',
            ]);
        }

        $outreach->update([
            'legal_rule_status' => $legal,
            'legal_ack_at' => $legal === LegalRuleStatus::PendingVerification->value ? now() : null,
        ]);

        if ($outreach->contact !== null) {
            $outreach->contact->update(['notified_at' => now()]);
        }
    }

    private function legalStatus(object $company, ScoutContactChannelEloquentModel $channel): ?string
    {
        $type = $channel->channel_type->value;

        if ($type === 'job_posting_apply') {
            return null;
        }

        if (in_array($type, ['contact_form', 'careers_form', 'company_network_page'], true)) {
            return in_array($company->country, ['ES', 'PT'], true) ? LegalRuleStatus::PendingVerification->value : null;
        }

        foreach ((array) config('lead-scout.contact_rules', []) as $rule) {
            if (mb_strtoupper((string) ($rule['country'] ?? '')) === mb_strtoupper((string) $company->country)
                && ($rule['medium'] ?? null) === 'email') {
                return $rule['legal_status'];
            }
        }

        return null;
    }
}
