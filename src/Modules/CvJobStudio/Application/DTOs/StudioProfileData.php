<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Profile create/update shape (fused: ≥90% identical). Gates, stack lock,
 * geography, languages, seniority band and protected block are profile data,
 * never engine constants (FR-30). `rules` carries the full ruleset versioned
 * snapshot; null means "use the config defaults at score time".
 *
 * `skillFloors` is the per-skill defensibility the shortlist filter reads:
 * `{"Laravel": 4, "React": 2}`. It is a FILTER, never a score input — see the
 * `skill_floors` migration for why it must stay out of `creditFor()`.
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
        /** @var array<string, int>|null */
        public readonly ?array $skillFloors = null,
        /** @var list<string>|null */
        public readonly ?array $geographyDeny = null,
        public readonly ?int $yearsBaseline = null,
        public readonly ?string $uuid = null,
    ) {}

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
            'skill_floors' => ['nullable', 'array'],
            // For `skill_floors => ['Laravel' => 4]`: `.*` validates the VALUE
            // (the level) and `.*.*` validates the KEY (the skill name).
            'skill_floors.*' => ['required', 'integer', 'min:0', 'max:4'],
            'skill_floors.*.*' => ['required', 'string', 'max:64'],
            'geography_deny' => ['nullable', 'array'],
            'years_baseline' => ['nullable', 'integer', 'min:0', 'max:50'],
        ];
    }
}
