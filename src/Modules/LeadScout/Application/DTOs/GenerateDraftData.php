<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Enums\MessageVariant;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Draft request (plan §5 `GenerateDraftData`): optional posting, variant,
 * language and selector-chosen provider/model (validated vs catalog).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class GenerateDraftData extends Data
{
    public function __construct(
        public ?string $jobPostingId = null,
        public ?string $variant = null,
        public ?string $language = null,
        public ?string $provider = null,
        public ?string $model = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'jobPostingId' => ['nullable', 'uuid'],
            'variant' => ['nullable', 'string', 'in:'.implode(',', MessageVariant::values())],
            'language' => ['nullable', 'string', 'in:es,pt,en'],
            'provider' => ['nullable', 'string', 'max:32'],
            'model' => ['nullable', 'string', 'max:64'],
        ];
    }
}
