<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Opportunity policy edit (T-135, FR-51): validated by `OpportunityPolicy`
 * (every factor in [0.1, 1] with grade + source) before it touches the
 * profile — the handler parses, never trusts.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class UpdateOpportunityPolicyData extends Data
{
    /** @param  array<string, array{value: float, grade: string, source: string}>  $channels */
    public function __construct(
        public readonly array $channels,
        public readonly bool $neutral = false,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'channels' => ['required', 'array'],
            'channels.*.value' => ['required', 'numeric', 'min:0.1', 'max:1'],
            'channels.*.grade' => ['required', 'string', 'max:4'],
            'channels.*.source' => ['required', 'string', 'max:255'],
            'neutral' => ['nullable', 'boolean'],
        ];
    }
}
