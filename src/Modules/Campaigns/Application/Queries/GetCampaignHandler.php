<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Campaigns\Domain\Ports\CampaignRepositoryPort;
use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;

final readonly class GetCampaignHandler
{
    public function __construct(private CampaignRepositoryPort $campaigns) {}

    public function handle(string $uuid): CampaignEloquentModel
    {
        return $this->campaigns->findByUuid($uuid)
            ?? throw (new ModelNotFoundException)->setModel(CampaignEloquentModel::class, [$uuid]);
    }
}
