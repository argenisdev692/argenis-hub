<?php

declare(strict_types=1);

namespace Modules\Clients\Application\Commands;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;

final readonly class DeleteClientHandler
{
    /**
     * @throws ModelNotFoundException<ClientEloquentModel>
     */
    public function handle(string $uuid): void
    {
        ClientEloquentModel::query()->where('uuid', $uuid)->firstOrFail()->delete();
    }
}
