<?php

declare(strict_types=1);

use Modules\LeadScout\Domain\Services\ChannelAdvisor;

function advisor(): ChannelAdvisor
{
    return app(ChannelAdvisor::class);
}

function channel(string $type, ?string $uuid = null, ?string $url = 'https://agencia.example/contacto'): array
{
    return [
        'uuid' => $uuid ?? (string) Str::uuid7(),
        'type' => $type,
        'url' => $url,
        'generic_email' => null,
        'status' => 'active',
        'audience' => null,
    ];
}

function advisorContext(array $overrides = []): array
{
    return [
        'country' => 'ES',
        'has_offer' => true,
        'is_employment_offer' => false,
        'discovery_without_offer' => false,
        'has_decisor' => true,
        'nominative_email' => null,
        'dgc_listed' => false,
        'dgc_list_stale' => false,
        ...$overrides,
    ];
}

function advisorRules(): array
{
    return config('lead-scout.contact_rules');
}

it('orders channels legally before commercially', function (): void {
    $result = advisor()->advise(
        [
            channel('careers_form'),
            channel('generic_email'),
            channel('contact_form'),
            channel('job_posting_apply', null, 'https://ofertas.example.com/1'),
        ],
        advisorContext(),
        advisorRules(),
    );

    $order = array_column($result['ranked'], 'type');

    expect($order)->toBe(['job_posting_apply', 'contact_form', 'generic_email', 'careers_form'])
        ->and($result['ranked'][0]['allowed'])->toBeTrue()
        ->and($result['ranked'][2]['allowed'])->toBeFalse()
        ->and($result['ranked'][2]['blocked_reason'])->toContain('LSSI')
        ->and($result['ranked'][3]['allowed'])->toBeTrue()
        ->and($result['ranked'][3]['warning'])->toContain('HR/recruiters')
        ->and($result['recommended_uuid'])->toBe($result['ranked'][0]['uuid'])
        ->and($result['cold_email_allowed'])->toBeFalse();
});

it('withholds pending generic mailboxes until verified', function (): void {
    $pending = advisor()->advise(
        [channel('generic_email', null, 'info@agencia.pt')],
        advisorContext(['country' => 'PT']),
        advisorRules(),
    );

    expect($pending['ranked'][0]['allowed'])->toBeFalse()
        ->and($pending['ranked'][0]['warning'])->toContain('pending');

    $verified = advisor()->advise(
        [channel('generic_email', null, 'info@agencia.pt')],
        advisorContext(['country' => 'PT']),
        [[
            'country' => 'PT', 'medium' => 'email', 'mailbox' => 'generic',
            'decision' => 'allow', 'legal_status' => 'verified',
        ]],
    );

    expect($verified['ranked'][0]['allowed'])->toBeTrue()
        ->and($verified['recommended_uuid'])->not->toBeNull();
});

it('never cold-emails countries without a rule and never applies without offers', function (): void {
    $nl = advisor()->advise(
        [channel('generic_email', null, 'info@agency.nl')],
        advisorContext(['country' => 'NL']),
        advisorRules(),
    );

    expect($nl['ranked'][0]['allowed'])->toBeFalse();

    $discovery = advisor()->advise(
        [channel('job_posting_apply', null, 'https://ofertas.example.com/1')],
        advisorContext(['discovery_without_offer' => true, 'has_offer' => false]),
        advisorRules(),
    );

    expect($discovery['ranked'][0]['allowed'])->toBeFalse()
        ->and($discovery['recommended_uuid'])->toBeNull();
});

it('blocks dgc-listed and stale-list pt mailboxes and flags nominative mail', function (): void {
    $listed = advisor()->advise(
        [channel('generic_email', null, 'geral@agencia.pt')],
        advisorContext(['country' => 'PT', 'dgc_listed' => true]),
        [[
            'country' => 'PT', 'medium' => 'email', 'mailbox' => 'generic',
            'decision' => 'allow', 'legal_status' => 'verified',
        ]],
    );

    expect($listed['ranked'][0]['allowed'])->toBeFalse()
        ->and($listed['ranked'][0]['blocked_reason'])->toContain('DGC');

    $nominative = advisor()->advise(
        [],
        advisorContext(['country' => 'PT', 'nominative_email' => 'ana@agencia.pt']),
        advisorRules(),
    );

    expect($nominative['ranked'][0]['type'])->toBe('nominative_email')
        ->and($nominative['ranked'][0]['allowed'])->toBeFalse();
});

it('prefers forms without a decisor and reports no channel when empty', function (): void {
    $result = advisor()->advise(
        [
            channel('generic_email', null, 'info@agencia.pt'),
            channel('contact_form'),
        ],
        advisorContext(['country' => 'PT', 'has_decisor' => false]),
        [[
            'country' => 'PT', 'medium' => 'email', 'mailbox' => 'generic',
            'decision' => 'allow', 'legal_status' => 'verified',
        ]],
    );

    $recommended = null;

    foreach ($result['ranked'] as $candidate) {
        if ($candidate['uuid'] === $result['recommended_uuid']) {
            $recommended = $candidate;
        }
    }

    expect($recommended['type'])->toBe('contact_form')
        ->and(advisor()->advise([], advisorContext(), advisorRules())['recommended_uuid'])->toBeNull();
});
