<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Profile create/update shape (fused: ≥90% identical). Gates, stack lock,
 * geography, languages, seniority band and protected block are profile data,
 * never engine constants (FR-30). `rules` carries the full ruleset versioned
 * snapshot; null means "use the config defaults at score time".
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioProfileData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $baseCity = null,
        public readonly ?string $baseCountry = null,
        /** @var list<string>|null */
        public readonly ?array $acceptedRemoteScopes = null,
        /** @var list<string>|null */
        public readonly ?array $stackMust = null,
        /** @var list<string>|null */
        public readonly ?array $stackReject = null,
        /** @var list<string>|null */
        public readonly ?array $geographyDeny = null,
        public readonly ?int $yearsBaseline = null,
        public readonly ?string $uuid = null,
    ) {}

    public static function fromModel(StudioProfileEloquentModel $profile): self
    {
        return new self(
            name: $profile->name,
            slug: $profile->slug,
            baseCity: $profile->base_city,
            baseCountry: $profile->base_country,
            acceptedRemoteScopes: $profile->accepted_remote_scopes,
            stackMust: $profile->stack_must,
            stackReject: $profile->stack_reject,
            geographyDeny: $profile->geography_deny,
            yearsBaseline: $profile->years_baseline,
            uuid: $profile->uuid,
        );
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'base_city' => ['nullable', 'string', 'max:128'],
            'base_country' => ['nullable', 'string', 'max:8'],
            'accepted_remote_scopes' => ['nullable', 'array'],
            'accepted_remote_scopes.*' => ['string', 'in:remote_global,remote_eu,remote_pt_es,remote_unclear,hybrid_local'],
            'stack_must' => ['nullable', 'array'],
            'stack_reject' => ['nullable', 'array'],
            'geography_deny' => ['nullable', 'array'],
            'years_baseline' => ['nullable', 'integer', 'min:0', 'max:50'],
        ];
    }
}
