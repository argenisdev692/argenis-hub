<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Suppression intake (spec FR-17): canonical domain + reason.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class SuppressData extends Data
{
    public function __construct(
        public string $domain,
        public string $reason,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
