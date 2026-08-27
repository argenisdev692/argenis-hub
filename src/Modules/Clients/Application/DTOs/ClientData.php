<?php

declare(strict_types=1);

namespace Modules\Clients\Application\DTOs;

use Modules\Clients\Domain\Enums\ClientStatus;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Admin-facing representation of a CRM client — the allowlist behind every
 * `/data/admin/clients` response. The auto-increment `id` and the owning
 * `user_id` never cross this boundary (OWASP §12); the owner is surfaced only
 * in the export, from an explicitly eager-loaded relation.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ClientData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $clientName,
        public readonly ?string $email,
        public readonly ClientStatus $status,
        public readonly string $phone,
        public readonly ?string $address,
        public readonly ?string $country,
        public readonly ?string $countryCode,
        public readonly ?string $taxId,
        public readonly ?string $nif,
        public readonly ?string $website,
        public readonly ?string $facebookLink,
        public readonly ?string $instagramLink,
        public readonly ?string $linkedinLink,
        public readonly ?string $twitterLink,
        public readonly ?string $notes,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly ?string $deletedAt,
    ) {}

    public static function fromModel(ClientEloquentModel $client): self
    {
        return new self(
            uuid: $client->uuid,
            clientName: $client->client_name,
            email: $client->email,
            status: $client->status,
            phone: $client->phone,
            address: $client->address,
            country: $client->country,
            countryCode: $client->country_code,
            taxId: $client->tax_id,
            nif: $client->nif,
            website: $client->website,
            facebookLink: $client->facebook_link,
            instagramLink: $client->instagram_link,
            linkedinLink: $client->linkedin_link,
            twitterLink: $client->twitter_link,
            notes: $client->notes,
            createdAt: $client->created_at?->toIso8601String(),
            updatedAt: $client->updated_at?->toIso8601String(),
            deletedAt: $client->deleted_at?->toIso8601String(),
        );
    }
}
