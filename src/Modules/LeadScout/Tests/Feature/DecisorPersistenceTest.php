<?php

declare(strict_types=1);

use Database\Factories\ScoutCompanyFactory;
use Database\Factories\ScoutScoreResultFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadScout\Application\Commands\ExtractDecisionMakersHandler;
use Modules\LeadScout\Domain\Enums\Tier;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutFetchedPageEloquentModel;

uses(RefreshDatabase::class);

function companyWithTeamPage(Tier $tier): ScoutCompanyEloquentModel
{
    $company = ScoutCompanyFactory::new()->create(['canonical_domain' => 'agencia.example']);
    ScoutScoreResultFactory::new()->forCompany($company)->create(['tier' => $tier]);

    ScoutFetchedPageEloquentModel::query()->create([
        'company_id' => $company->id,
        'url' => 'https://agencia.example/equipo',
        'page_type' => 'team',
        'content_markdown' => implode("\n", [
            '# Nuestro equipo',
            '',
            '**Ana Ruiz** — CEO y fundadora',
            'ana@agencia.example',
            '',
            '**João Silva** — CTO',
            '',
            'Somos 12 personas.',
        ]),
        'fetched_at' => now(),
    ]);

    return $company;
}

it('persists the decisors of a tier A lead (FR-27)', function (): void {
    $company = companyWithTeamPage(Tier::A);

    $report = app(ExtractDecisionMakersHandler::class)->persistIfTierAB($company->uuid);

    expect($report['persisted'])->toBeGreaterThan(0)
        ->and(ScoutContactEloquentModel::query()->where('company_id', $company->id)->count())->toBe($report['persisted'])
        ->and($company->refresh()->has_decision_maker)->toBeTrue();
});

it('stores no decisor for a tier C lead', function (): void {
    $company = companyWithTeamPage(Tier::C);

    $report = app(ExtractDecisionMakersHandler::class)->persistIfTierAB($company->uuid);

    expect($report['persisted'])->toBe(0)
        ->and(ScoutContactEloquentModel::query()->where('company_id', $company->id)->count())->toBe(0);
});
