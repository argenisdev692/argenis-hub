<?php

declare(strict_types=1);

namespace Modules\Clients\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Clients\Application\DTOs\ClientData;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;

final readonly class GetClientHandler
{
    /**
     * @throws ModelNotFoundException<ClientEloquentModel>
     */
    #[\NoDiscard('handle() returns the client.')]
    public function handle(string $uuid): ClientData
    {
        // withTrashed(): the admin detail view must also reach a suspended row —
        // it is the only way to preview one before restoring it.
        $client = ClientEloquentModel::withTrashed()->where('uuid', $uuid)->firstOrFail();

        return ClientData::fromModel($client);
    }
}
