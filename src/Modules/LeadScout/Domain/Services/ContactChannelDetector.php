<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

use Modules\LeadScout\Domain\Enums\ChannelAudience;
use Modules\LeadScout\Domain\Enums\ChannelType;

/**
 * Deterministic contact-channel detection (spec US-12, FR-31/32): page
 * markdown + form summaries + the offer → typed channels with evidence and
 * likely audience. Never fills, sends or invokes a form; never extracts
 * phones (there is no phone pattern in this class, by design).
 *
 * `careers_form` is always extracted when present, with the HR warning
 * attached downstream. Invitation wording doubles as a verified commercial
 * signal (`impliedSignals`, +35 plan §3.3).
 */
final readonly class ContactChannelDetector
{
    /**
     * @var list<string>
     */
    private const array GENERIC_LOCALS = ['info', 'geral', 'hola', 'contact', 'contacto', 'hello'];

    /**
     * @var list<string>
     */
    private const array NETWORK_HOSTS = [
        'linkedin.com', 'facebook.com', 'instagram.com', 'x.com', 'twitter.com', 'youtube.com',
    ];

    /**
     * @param  array<int, array{url: string, markdown: string, forms_summary: ?array}>  $pages
     * @param  array{url: ?string, apply_email: ?string}|null  $offer
     * @return array{channels: list<array{type: string, url: ?string, generic_email: ?string, form_fields: ?array, has_captcha: bool, audience: string, evidence_url: string, excerpt: string}>, impliedSignals: list<array{signal_key: string, excerpt: string, url: string}>}
     */
    #[\NoDiscard]
    public function detect(array $pages, string $companyDomain, ?array $offer = null): array
    {
        $channels = [];
        $implied = [];

        foreach ($pages as $page) {
            $url = $page['url'] ?? '';
            $markdown = $page['markdown'] ?? '';
            $forms = $page['forms_summary'] ?? null;

            if (trim($markdown) === '' && $forms === null) {
                continue;
            }

            $kind = $this->pageKind($url);

            if ($this->hasInvitation($markdown)) {
                $excerpt = $this->excerpt($markdown, ['freelancers', 'partner', 'colaboradores', 'parceiros', 'white label', 'marca blanca']);

                if ($kind === 'partners') {
                    $channels[] = $this->channel(ChannelType::PartnerPage->value, $url, $url, $excerpt, ChannelAudience::LeadershipSales->value);
                } else {
                    $channels[] = $this->channel(ChannelType::FreelanceCall->value, $url, $url, $excerpt, ChannelAudience::LeadershipSales->value);
                }

                $implied[] = ['signal_key' => 'accepts_external', 'excerpt' => $excerpt, 'url' => $url];
            }

            if ($kind === 'partners' && ! $this->hasType($channels, ChannelType::PartnerPage->value)) {
                $channels[] = $this->channel(
                    ChannelType::PartnerPage->value, $url, $url,
                    $this->excerpt($markdown, ['partner', 'parceiros', 'socios']) ?? mb_substr($url, 0, 300),
                    ChannelAudience::LeadershipSales->value,
                );
            }

            if (is_array($forms)) {
                foreach ($this->channelsFromForms($forms, $url, $kind) as $channel) {
                    $channels[] = $channel;
                }
            }

            foreach ($this->genericEmails($markdown, $companyDomain, $url) as $channel) {
                $channels[] = $channel;
            }

            foreach ($this->networkPages($markdown, $url) as $channel) {
                $channels[] = $channel;
            }
        }

        if ($offer !== null && (($offer['url'] ?? null) !== null || ($offer['apply_email'] ?? null) !== null)) {
            $channels[] = $this->channel(
                ChannelType::JobPostingApply->value,
                $offer['url'] ?? $offer['apply_email'],
                $offer['url'],
                'Application channel from the offer itself.',
                ChannelAudience::HrRecruiting->value,
            );
        }

        return ['channels' => $this->dedupe($channels), 'impliedSignals' => $implied];
    }

    private function pageKind(string $url): string
    {
        $lower = mb_strtolower($url);

        if (preg_match('~/(trabaja|empleo|careers|carreiras|join-?us|jobs|careers)/?~', $lower) === 1) {
            return 'careers';
        }

        if (preg_match('~/(partner|parceiros|socios|partners|colabora)(/|$)~', $lower) === 1) {
            return 'partners';
        }

        if (preg_match('~/(contacto|contactos|contact|kontakt)/?~', $lower) === 1) {
            return 'contact';
        }

        return 'other';
    }

    private function hasInvitation(string $markdown): bool
    {
        return preg_match('/trabajamos con freelancers|colaboradores externos|buscamos colaboradores|freelancers welcome|partner program|white label|marca blanca|subcontrata|queremos ser tu partner|parceiros/i', $markdown) === 1;
    }

    /**
     * @param  array{forms: list<array{fields: list<string>, has_textarea: bool, has_captcha: bool, action_host: ?string}>}|array  $forms
     * @return list<array{type: string, url: ?string, generic_email: ?string, form_fields: ?array, has_captcha: bool, audience: string, evidence_url: string, excerpt: string}>
     */
    private function channelsFromForms(array $forms, string $url, string $kind): array
    {
        $channels = [];

        foreach ((array) ($forms['forms'] ?? []) as $form) {
            $fields = array_map(strtolower(...), (array) ($form['fields'] ?? []));
            $hasContactFields = count(array_intersect($fields, ['email', 'e-mail', 'mensaje', 'message', 'mensagem', 'nombre', 'name', 'nome'])) >= 2;

            if (! $hasContactFields) {
                continue;
            }

            if ($kind === 'careers') {
                $channels[] = [
                    'type' => ChannelType::CareersForm->value,
                    'url' => $url,
                    'generic_email' => null,
                    'form_fields' => array_values($fields),
                    'has_captcha' => (bool) ($form['has_captcha'] ?? false),
                    'audience' => ChannelAudience::HrRecruiting->value,
                    'evidence_url' => $url,
                    'excerpt' => 'Employment form ('.implode(', ', array_slice(array_values($fields), 0, 6)).'). Low priority: usually reaches HR.',
                ];
            } else {
                $channels[] = [
                    'type' => ChannelType::ContactForm->value,
                    'url' => $url,
                    'generic_email' => null,
                    'form_fields' => array_values($fields),
                    'has_captcha' => (bool) ($form['has_captcha'] ?? false),
                    'audience' => ChannelAudience::LeadershipSales->value,
                    'evidence_url' => $url,
                    'excerpt' => 'Contact form ('.implode(', ', array_slice(array_values($fields), 0, 6)).').',
                ];
            }
        }

        return $channels;
    }

    /**
     * @return list<array{type: string, url: ?string, generic_email: ?string, form_fields: ?array, has_captcha: bool, audience: string, evidence_url: string, excerpt: string}>
     */
    private function genericEmails(string $markdown, string $companyDomain, string $url): array
    {
        $channels = [];
        $seen = [];

        if (preg_match_all('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $markdown, $found) === 0) {
            return [];
        }

        foreach ($found[0] as $email) {
            $parts = explode('@', mb_strtolower($email));

            if (count($parts) !== 2) {
                continue;
            }

            [$local, $host] = $parts;
            $host = (string) preg_replace('/^www\./', '', $host);

            if ($host !== mb_strtolower($companyDomain) || ! in_array($local, self::GENERIC_LOCALS, true) || isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;
            $channels[] = $this->channel(
                ChannelType::GenericEmail->value, $email, $url,
                "Generic company mailbox: {$email}.",
                ChannelAudience::Unknown->value,
                $email,
            );
        }

        return $channels;
    }

    /**
     * @return list<array{type: string, url: ?string, generic_email: ?string, form_fields: ?array, has_captcha: bool, audience: string, evidence_url: string, excerpt: string}>
     */
    private function networkPages(string $markdown, string $pageUrl): array
    {
        $channels = [];

        if (preg_match_all('/\((https?:\/\/[^\s)]+)\)/', $markdown, $found) === 0) {
            return [];
        }

        foreach (array_unique($found[1]) as $link) {
            $host = mb_strtolower((string) preg_replace('~^https?://(www\.)?~i', '', $link));
            $host = (string) strtok($host, '/');

            foreach (self::NETWORK_HOSTS as $network) {
                if ($host === $network || str_ends_with($host, '.'.$network)) {
                    $channels[] = $this->channel(
                        ChannelType::CompanyNetworkPage->value, $link, $pageUrl,
                        'Company page on a professional network (manual channel, never visited).',
                        ChannelAudience::Unknown->value,
                    );

                    break;
                }
            }
        }

        return $channels;
    }

    /**
     * @return array{type: string, url: ?string, generic_email: ?string, form_fields: ?array, has_captcha: bool, audience: string, evidence_url: string, excerpt: string}
     */
    private function channel(
        string $type,
        ?string $url,
        string $evidenceUrl,
        string $excerpt,
        string $audience,
        ?string $genericEmail = null,
    ): array {
        return [
            'type' => $type,
            'url' => $url,
            'generic_email' => $genericEmail,
            'form_fields' => null,
            'has_captcha' => false,
            'audience' => $audience,
            'evidence_url' => $evidenceUrl,
            'excerpt' => mb_substr($excerpt, 0, 300),
        ];
    }

    private function hasType(array $channels, string $type): bool
    {
        foreach ($channels as $channel) {
            if ($channel['type'] === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{type: string, url: ?string, generic_email: ?string, form_fields: ?array, has_captcha: bool, audience: string, evidence_url: string, excerpt: string}>  $channels
     * @return list<array{type: string, url: ?string, generic_email: ?string, form_fields: ?array, has_captcha: bool, audience: string, evidence_url: string, excerpt: string}>
     */
    private function dedupe(array $channels): array
    {
        $seen = [];
        $out = [];

        foreach ($channels as $channel) {
            $key = $channel['type'].'|'.($channel['url'] ?? '').'|'.($channel['generic_email'] ?? '');

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = $channel;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $cues
     */
    private function excerpt(string $markdown, array $cues): ?string
    {
        foreach ($cues as $cue) {
            $at = mb_stripos($markdown, $cue);

            if ($at !== false) {
                // Phones never reach stored evidence (FR-32).
                return PersonalDataScrubber::maskPhones(trim(mb_substr($markdown, max(0, $at - 120), 300)));
            }
        }

        return null;
    }
}
