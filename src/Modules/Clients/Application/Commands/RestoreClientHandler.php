<?php

declare(strict_types=1);

namespace Modules\Clients\Application\Commands;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Clients\Application\DTOs\ClientData;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;

final readonly class RestoreClientHandler
{
    /**
     * @throws ModelNotFoundException<ClientEloquentModel>
     */
    #[\NoDiscard('handle() returns the restored client.')]
    public function handle(string $uuid): ClientData
    {
        $client = ClientEloquentModel::withTrashed()->where('uuid', $uuid)->firstOrFail();
        $client->restore();

        return ClientData::fromModel($client);
    }
}
