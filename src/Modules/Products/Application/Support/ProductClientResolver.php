<?php

declare(strict_types=1);

namespace Modules\Products\Application\Support;

use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;

/**
 * Resolves the optional owning client of a catalog product.
 *
 * The Clients module ships no repository port (Repository Optionality Rule —
 * one Eloquent source, no decorator), so the lookup is a direct query, the same
 * way every other consumer reaches clients.
 */
final readonly class ProductClientResolver
{
    public static function idFor(?string $clientUuid): ?int
    {
        if ($clientUuid === null || $clientUuid === '') {
            return null;
        }

        $id = ClientEloquentModel::query()->where('uuid', $clientUuid)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
