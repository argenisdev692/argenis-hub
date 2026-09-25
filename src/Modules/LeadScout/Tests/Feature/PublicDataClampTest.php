<?php

declare(strict_types=1);

use Database\Factories\ScoutCompanyFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;

uses(RefreshDatabase::class);

it('clamps overlong public-data strings to the varchar columns', function (): void {
    $model = ScoutCompanyFactory::new()->create();
    $company = app(CompanyRepositoryPort::class)->byUuid($model->uuid);

    expect($company)->not->toBeNull();

    // A full markdown line, as PublicCompanyDataExtractor produces for
    // registry_info — Postgres varchar(255) rejected it with SQLSTATE 22001
    // and the EnrichCompanyJob died before extract/score were ever queued.
    app(CompanyRepositoryPort::class)->recordPublicData($company, [
        'registry_info' => str_repeat('Registro Mercantil de Madrid, Tomo 12345. ', 10),
        'city' => 'Madrid',
        'founded_year' => 2020,
    ], []);

    $stored = ScoutCompanyEloquentModel::query()->findOrFail($model->id);

    expect(mb_strlen((string) $stored->registry_info))->toBe(255)
        ->and(str_starts_with((string) $stored->registry_info, 'Registro Mercantil'))->toBeTrue()
        ->and($stored->city)->toBe('Madrid')
        ->and($stored->founded_year)->toBe(2020);
});
