<?php

declare(strict_types=1);

namespace Modules\Company\Infrastructure\Persistence\Repositories;

use App\Models\CompanyData;
use Modules\Company\Domain\Exceptions\CompanyNotConfigured;
use Modules\Company\Domain\Ports\CompanyRepositoryPort;
use Modules\Company\Domain\ValueObjects\CompanySnapshot;
use Modules\Company\Infrastructure\Persistence\Mappers\CompanyMapper;

/**
 * CompanyRepositoryPort over App\Models\CompanyData.
 *
 * The Eloquent model stays in app/Models rather than moving under this module,
 * and that is a boundary decision rather than an oversight: CompanyProfile in
 * the shared kernel reads the same row to brand every email and PDF export, and
 * the shared kernel is forbidden from importing a module (enforced by
 * tests/Architecture/LayersTest.php). The company record is genuinely shared
 * state; this module owns the EDITING of it.
 *
 * "The one company" is the lowest id, matching what CompanyProfile resolves, so
 * the settings screen and the branding cache can never disagree about which row
 * they mean.
 */
final readonly class EloquentCompanyRepository implements CompanyRepositoryPort
{
    public function current(): CompanySnapshot
    {
        return CompanyMapper::toSnapshot($this->model());
    }

    public function save(CompanySnapshot $company): CompanySnapshot
    {
        $model = $this->model();

        $model->fill(CompanyMapper::toColumns($company));
        $model->save();

        // The model saved hook flushes the branding caches, so re-reading here
        // returns the same values the rest of the application will now see.
        return CompanyMapper::toSnapshot($model->refresh());
    }

    private function model(): CompanyData
    {
        return CompanyData::query()->orderBy('id')->first()
            ?? throw CompanyNotConfigured::make();
    }
}
