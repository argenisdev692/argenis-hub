<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentRepositoryPort;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;

final readonly class GetSocialMediaContentHandler
{
    public function __construct(private SocialMediaContentRepositoryPort $content) {}

    public function handle(string $uuid): SocialMediaContentEloquentModel
    {
        return $this->content->findByUuid($uuid)
            ?? throw (new ModelNotFoundException)->setModel(SocialMediaContentEloquentModel::class, [$uuid]);
    }
}
