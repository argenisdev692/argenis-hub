<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Entities\ScoreResult;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Score snapshot response (spec US-4, plan §5 `ScoreResultData`).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ScoreResultData extends Data
{
    /**
     * @param  array<string, int>  $subscores
     * @param  list<array{signal_key: string, points: int, explanation: string}>  $reasons
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $companyUuid,
        public readonly array $subscores,
        public readonly int $leadScore,
        public readonly int $confidence,
        public readonly string $tier,
        public readonly ?string $discardReason,
        public readonly array $reasons,
        public readonly string $rulesVersion,
    ) {}

    public static function fromEntity(ScoreResult $result, string $companyUuid): self
    {
        return new self(
            uuid: $result->uuid,
            companyUuid: $companyUuid,
            subscores: $result->subscores,
            leadScore: $result->leadScore,
            confidence: $result->confidence,
            tier: $result->tier->value,
            discardReason: $result->discardReason?->value,
            reasons: array_map(static fn (array $reason): array => [
                'signal_key' => $reason['signal_key'] ?? 'unconfirmed_tech',
                'points' => $reason['points'],
                'explanation' => $reason['explanation'],
            ], $result->reasons),
            rulesVersion: $result->rulesVersion,
        );
    }
}
