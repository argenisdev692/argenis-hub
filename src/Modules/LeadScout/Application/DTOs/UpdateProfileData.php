<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Operator-tunable profile fields (spec US-1 CA-6): weights, languages,
 * minimum rate and target countries. Every save mints a new profile
 * version; later scores record which version they used.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateProfileData extends Data
{
    /**
     * @param  array<string, int>|null  $weights
     * @param  array<string, string>|null  $languages
     * @param  list<string>|null  $targetCountries
     */
    public function __construct(
        public ?array $weights = null,
        public ?array $languages = null,
        public ?int $minRateCents = null,
        public ?array $targetCountries = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'weights' => ['nullable', 'array'],
            'weights.*' => ['integer', 'min:0', 'max:100'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:32'],
            'minRateCents' => ['nullable', 'integer', 'min:0'],
            'targetCountries' => ['nullable', 'array', 'max:30'],
            'targetCountries.*' => ['string', 'size:2'],
        ];
    }
}
