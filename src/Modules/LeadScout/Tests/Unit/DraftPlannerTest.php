<?php

declare(strict_types=1);

use Modules\LeadScout\Domain\Entities\Company;
use Modules\LeadScout\Domain\Entities\Signal;
use Modules\LeadScout\Domain\Enums\ActivityStatus;
use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Enums\EmployeeRange;
use Modules\LeadScout\Domain\Enums\ExtractionMethod;
use Modules\LeadScout\Domain\Enums\MessageVariant;
use Modules\LeadScout\Domain\Enums\SignalDimension;
use Modules\LeadScout\Domain\Enums\SignalNature;
use Modules\LeadScout\Domain\Services\DraftPlanner;
use Modules\LeadScout\Domain\ValueObjects\DraftContext;

function plannerCompany(array $sectors = []): Company
{
    return new Company(
        id: 1, uuid: 'c-1', canonicalDomain: 'agencia.example', name: 'Agencia', country: 'ES',
        origin: CompanyOrigin::Discovery, originRef: null, discoveryWave: 'wave1', companyType: null,
        employeeRange: EmployeeRange::Unknown, teamSizeObserved: null, hasDecisionMaker: false,
        needsResearch: false, activityStatus: ActivityStatus::Active, lastActivityAt: null,
        timezoneOverlapHours: null, legalName: null, legalForm: null, taxId: null, registryInfo: null,
        city: null, foundedYear: null, aliases: [], services: [], sectors: $sectors, siteLanguages: [],
        clientCompanies: [], publicUrls: [], publicDataEvidence: [], createdAt: null, updatedAt: null,
    );
}

function plannerSignal(string $key, SignalDimension $dimension = SignalDimension::Technical, ?string $value = null): Signal
{
    return new Signal(
        id: 1, companyId: 1, dimension: $dimension, signalKey: $key, valueText: $value,
        nature: SignalNature::Fact, confidence: 80, evidenceUrl: null, evidenceExcerpt: null,
        capturedAt: null, extractionMethod: ExtractionMethod::Rule, aiProvider: null, aiModel: null,
    );
}

/**
 * @param  list<Signal>  $signals
 */
function plannerContext(array $signals, bool $hasOffer = false, array $sectors = []): DraftContext
{
    return new DraftContext(plannerCompany($sectors), $signals, $hasOffer, [], [], null);
}

it('picks the variant from offer, stack, legacy and sector evidence', function (): void {
    $planner = new DraftPlanner;

    expect($planner->variant(plannerContext([], hasOffer: true)))->toBe(MessageVariant::Vacancy)
        ->and($planner->variant(plannerContext([plannerSignal('laravel')])))->toBe(MessageVariant::Stack)
        ->and($planner->variant(plannerContext([plannerSignal('maintenance_sla', SignalDimension::Recurrent)])))->toBe(MessageVariant::Legacy)
        ->and($planner->variant(plannerContext([], sectors: ['hospitality'])))->toBe(MessageVariant::Sector)
        ->and($planner->variant(plannerContext([])))->toBe(MessageVariant::Stack);
});

it('writes in Portuguese, Spanish or English by market', function (): void {
    $planner = new DraftPlanner;

    expect($planner->language('PT'))->toBe('pt')
        ->and($planner->language('ES'))->toBe('es')
        ->and($planner->language('MX'))->toBe('es')
        ->and($planner->language('NL'))->toBe('en')
        ->and($planner->language(null))->toBe('en');
});

it('leans on the variant signal, else the strongest one, and prefers an explicit posting', function (): void {
    $planner = new DraftPlanner;
    $context = plannerContext([plannerSignal('remote', SignalDimension::Remote), plannerSignal('laravel')]);

    expect($planner->signalUsed($context, 'stack', null))->toBe('laravel')
        ->and($planner->signalUsed($context, 'vacancy', null))->toBe('remote')
        ->and($planner->signalUsed($context, 'stack', 'posting-uuid'))->toBe('posting-uuid')
        ->and($planner->companyTechs([plannerSignal('laravel', value: 'Laravel 12'), plannerSignal('remote', SignalDimension::Remote, 'Remote')]))->toBe(['Laravel 12']);
});

it('explains the chosen channel or the first block', function (): void {
    $planner = new DraftPlanner;
    $advice = ['ranked' => [
        ['uuid' => 'a', 'blocked_reason' => 'No cold email in ES', 'warning' => null],
        ['uuid' => 'b', 'blocked_reason' => null, 'warning' => 'HR audience'],
    ]];

    expect($planner->channelWarning($advice, 'b'))->toBe('HR audience')
        ->and($planner->channelWarning($advice, null))->toBe('No cold email in ES')
        ->and($planner->channelWarning(['ranked' => []], null))->toBe('No permitted channel: manual review required.');
});
