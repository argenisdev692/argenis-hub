<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Enums\ChannelStatus;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Channel re-verification (spec US-12, plan §5 `UpdateChannelData`):
 * a channel that no longer exists is marked `broken` and stops being
 * recommended. No other transition exists.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class UpdateChannelData extends Data
{
    public function __construct(
        public string $status,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:'.ChannelStatus::Active->value.','.ChannelStatus::Broken->value],
        ];
    }
}
