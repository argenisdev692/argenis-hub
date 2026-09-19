<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

use Modules\LeadScout\Domain\Enums\ChannelStatus;
use Modules\LeadScout\Domain\Enums\ChannelType;
use Modules\LeadScout\Domain\Enums\LegalRuleStatus;

/**
 * Legal × commercial channel ordering (spec US-12, T061):
 * offer/freelance-call → partners → contact form → professional network
 * (manual) → generic mailbox (only where legal) → careers form (HR
 * warning) → nominative email (never cold).
 *
 * Country rules come from config with legal state (FR-44): `block` applies
 * even while pending; `allow` needs `verified`, otherwise the channel
 * carries a «pending verification» warning and is not recommended.
 * Countries without any rule (waves 2-3 until OP-26) never get cold email.
 */
final readonly class ChannelAdvisor
{
    /**
     * Legal state a send through this channel carries (spec FR-44):
     * applying to an offer needs none; forms and company network pages in
     * ES/PT stay pending verification; email follows the country rule.
     *
     * @param  list<array{country: string, medium: string, mailbox: string, decision: string, legal_status: string}>  $rules
     */
    public function legalRuleStatus(ChannelType $type, ?string $country, array $rules): ?LegalRuleStatus
    {
        return match ($type) {
            ChannelType::JobPostingApply => null,
            ChannelType::ContactForm, ChannelType::CareersForm, ChannelType::CompanyNetworkPage => in_array($country, ['ES', 'PT'], true)
                ? LegalRuleStatus::PendingVerification
                : null,
            default => $this->emailRuleStatus($country, $rules),
        };
    }

    /**
     * @param  list<array{country: string, medium: string, mailbox: string, decision: string, legal_status: string}>  $rules
     */
    private function emailRuleStatus(?string $country, array $rules): ?LegalRuleStatus
    {
        foreach ($rules as $rule) {
            if (mb_strtoupper($rule['country']) === mb_strtoupper((string) $country) && $rule['medium'] === 'email') {
                return LegalRuleStatus::tryFrom($rule['legal_status']);
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{uuid: string, type: string, url: ?string, generic_email: ?string, status: string, audience: ?string}>  $channels
     * @param  array{country: ?string, has_offer: bool, is_employment_offer: bool, discovery_without_offer: bool, has_decisor: bool, nominative_email: ?string, dgc_listed: bool, dgc_list_stale: bool}  $context
     * @param  list<array{country: string, medium: string, mailbox: string, decision: string, legal_status: string}>  $rules
     * @return array{ranked: list<array{uuid: ?string, type: string, url: ?string, rank: int, allowed: bool, blocked_reason: ?string, warning: ?string}>, recommended_uuid: ?string, cold_email_allowed: bool, employment_application: bool}
     */
    #[\NoDiscard]
    public function advise(array $channels, array $context, array $rules): array
    {
        $ranked = [];
        $rank = 0;

        foreach ($this->orderedTypes() as $type) {
            foreach ($channels as $channel) {
                if (($channel['type'] ?? null) !== $type || ($channel['status'] ?? null) !== ChannelStatus::Active->value) {
                    continue;
                }

                $rank++;
                $ranked[] = $this->evaluate($channel, $type, $rank, $context, $rules);
            }
        }

        if (($context['nominative_email'] ?? null) !== null) {
            $rank++;
            $ranked[] = [
                'uuid' => null,
                'type' => 'nominative_email',
                'url' => $context['nominative_email'],
                'rank' => $rank,
                'allowed' => false,
                'blocked_reason' => $this->nominativeReason((string) $context['country']),
                'warning' => null,
            ];
        }

        $recommended = null;

        foreach ($ranked as $candidate) {
            if ($candidate['allowed']) {
                $recommended = $candidate['uuid'];
                break;
            }
        }

        // Without a decisor the operator works forms/offers, not inboxes.
        if (($context['has_decisor'] ?? false) === false && $recommended !== null) {
            foreach ($ranked as $candidate) {
                if ($candidate['allowed'] && in_array($candidate['type'], [
                    ChannelType::JobPostingApply->value, ChannelType::ContactForm->value, ChannelType::CareersForm->value,
                ], true)) {
                    $recommended = $candidate['uuid'];
                    break;
                }
            }
        }

        return [
            'ranked' => $ranked,
            'recommended_uuid' => $recommended,
            'cold_email_allowed' => false,
            'employment_application' => (bool) ($context['is_employment_offer'] ?? false),
        ];
    }

    /**
     * @return list<string>
     */
    private function orderedTypes(): array
    {
        return [
            ChannelType::JobPostingApply->value,
            ChannelType::FreelanceCall->value,
            ChannelType::PartnerPage->value,
            ChannelType::ContactForm->value,
            ChannelType::CompanyNetworkPage->value,
            ChannelType::GenericEmail->value,
            ChannelType::CareersForm->value,
        ];
    }

    /**
     * @param  array{uuid: string, type: string, url: ?string, generic_email: ?string, status: string, audience: ?string}  $channel
     * @param  array{country: ?string, has_offer: bool, is_employment_offer: bool, discovery_without_offer: bool, has_decisor: bool, nominative_email: ?string, dgc_listed: bool, dgc_list_stale: bool}  $context
     * @param  list<array{country: string, medium: string, mailbox: string, decision: string, legal_status: string}>  $rules
     * @return array{uuid: ?string, type: string, url: ?string, rank: int, allowed: bool, blocked_reason: ?string, warning: ?string}
     */
    private function evaluate(array $channel, string $type, int $rank, array $context, array $rules): array
    {
        $country = (string) ($context['country'] ?? '');

        if ($type === ChannelType::JobPostingApply->value && ($context['discovery_without_offer'] ?? false)) {
            return $this->verdict($channel, $rank, false, 'Discovery lead without an offer: there is nothing to reply to.');
        }

        if ($type === ChannelType::JobPostingApply->value && ! ($context['has_offer'] ?? false)) {
            return $this->verdict($channel, $rank, false, 'No published offer to reply to.');
        }

        if ($type === ChannelType::GenericEmail->value) {
            return $this->evaluateGenericEmail($channel, $rank, $country, $context, $rules);
        }

        if ($type === ChannelType::CareersForm->value) {
            return $this->verdict(
                $channel,
                $rank,
                true,
                null,
                'Usually reaches HR/recruiters: present it as freelance collaboration, not as a job application.',
            );
        }

        if (in_array($type, [ChannelType::ContactForm->value, ChannelType::CompanyNetworkPage->value], true)) {
            $warning = in_array($country, ['ES', 'PT'], true)
                ? 'Commercial use pending legal verification (OP-15); sent manually only.'
                : null;

            return $this->verdict($channel, $rank, true, null, $warning);
        }

        return $this->verdict($channel, $rank, true, null, null);
    }

    /**
     * @param  array{uuid: string, type: string, url: ?string, generic_email: ?string, status: string, audience: ?string}  $channel
     * @param  array{country: ?string, has_offer: bool, is_employment_offer: bool, discovery_without_offer: bool, has_decisor: bool, nominative_email: ?string, dgc_listed: bool, dgc_list_stale: bool}  $context
     * @param  list<array{country: string, medium: string, mailbox: string, decision: string, legal_status: string}>  $rules
     * @return array{uuid: ?string, type: string, url: ?string, rank: int, allowed: bool, blocked_reason: ?string, warning: ?string}
     */
    private function evaluateGenericEmail(array $channel, int $rank, string $country, array $context, array $rules): array
    {
        if (($context['dgc_listed'] ?? false) === true) {
            return $this->verdict($channel, $rank, false, 'Company opposes direct marketing (DGC list, Lei 41/2004 art. 13-B).');
        }

        if (($context['dgc_list_stale'] ?? false) === true && $country === 'PT') {
            return $this->verdict($channel, $rank, false, 'DGC list older than 3 months: email blocked until renewed.');
        }

        $rule = $this->findRule($rules, $country, 'email', 'generic');

        if ($rule === null) {
            return $this->verdict($channel, $rank, false, 'No verified cold-email rule for this country (OP-26): offer, form or network only.');
        }

        if ($rule['decision'] === 'block') {
            return $this->verdict($channel, $rank, false, 'Cold email blocked here: '.($rule['reason'] ?? 'country rule'));
        }

        if ($rule['legal_status'] !== LegalRuleStatus::Verified->value) {
            return $this->verdict($channel, $rank, false, 'Generic-mailbox email pending legal verification: not recommended.', 'Verification pending.');
        }

        return $this->verdict($channel, $rank, true, null, 'Company mailbox with opt-out and valid sender required.');
    }

    /**
     * @param  array{uuid: string, type: string, url: ?string, generic_email: ?string, status: string, audience: ?string}  $channel
     * @return array{uuid: ?string, type: string, url: ?string, rank: int, allowed: bool, blocked_reason: ?string, warning: ?string}
     */
    private function verdict(array $channel, int $rank, bool $allowed, ?string $blockedReason, ?string $warning = null): array
    {
        return [
            'uuid' => $channel['uuid'],
            'type' => $channel['type'],
            'url' => $channel['url'],
            'rank' => $rank,
            'allowed' => $allowed,
            'blocked_reason' => $blockedReason,
            'warning' => $warning,
        ];
    }

    /**
     * @param  list<array{country: string, medium: string, mailbox: string, decision: string, legal_status: string}>  $rules
     * @return array{country: string, medium: string, mailbox: string, decision: string, legal_status: string}|null
     */
    private function findRule(array $rules, string $country, string $medium, string $mailbox): ?array
    {
        foreach ($rules as $rule) {
            if (mb_strtoupper((string) ($rule['country'] ?? '')) === mb_strtoupper($country)
                && ($rule['medium'] ?? null) === $medium
                && ($rule['mailbox'] ?? null) === $mailbox) {
                return $rule;
            }
        }

        return null;
    }

    private function nominativeReason(string $country): string
    {
        return match (mb_strtoupper($country)) {
            'ES' => 'Nominative email never used cold in ES (LSSI art. 21).',
            'PT' => 'Nominative email is a grey zone in PT: not recommended cold.',
            default => 'Nominative email never used cold.',
        };
    }
}
