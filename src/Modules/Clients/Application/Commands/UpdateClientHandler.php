<?php

declare(strict_types=1);

namespace Modules\Clients\Application\Commands;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Clients\Application\DTOs\ClientData;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;

final readonly class UpdateClientHandler
{
    /**
     * @param  array{
     *     client_name: string,
     *     email?: ?string,
     *     status?: ?string,
     *     phone: string,
     *     address?: ?string,
     *     country?: ?string,
     *     country_code?: ?string,
     *     tax_id?: ?string,
     *     nif?: ?string,
     *     website?: ?string,
     *     facebook_link?: ?string,
     *     instagram_link?: ?string,
     *     linkedin_link?: ?string,
     *     twitter_link?: ?string,
     *     notes?: ?string
     * }  $attributes
     *
     * @throws ModelNotFoundException<ClientEloquentModel>
     */
    #[\NoDiscard('handle() returns the updated client.')]
    public function handle(string $uuid, array $attributes): ClientData
    {
        $client = ClientEloquentModel::query()->where('uuid', $uuid)->firstOrFail();

        $client->update([
            'client_name' => $attributes['client_name'],
            'email' => $attributes['email'] ?? null,
            'status' => $attributes['status'] ?? $client->status->value,
            'phone' => $attributes['phone'],
            'address' => $attributes['address'] ?? null,
            'country' => $attributes['country'] ?? null,
            'country_code' => $attributes['country_code'] ?? null,
            'tax_id' => $attributes['tax_id'] ?? null,
            'nif' => $attributes['nif'] ?? null,
            'website' => $attributes['website'] ?? null,
            'facebook_link' => $attributes['facebook_link'] ?? null,
            'instagram_link' => $attributes['instagram_link'] ?? null,
            'linkedin_link' => $attributes['linkedin_link'] ?? null,
            'twitter_link' => $attributes['twitter_link'] ?? null,
            'notes' => $attributes['notes'] ?? null,
        ]);

        return ClientData::fromModel($client);
    }
}
