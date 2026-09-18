<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * AI default change (plan §5 `UpdateAiSettingsData`). Catalog membership
 * and credential availability are enforced in the handler (422).
 */
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateAiSettingsData extends Data
{
    public function __construct(
        public string $purpose,
        public string $provider,
        public string $model,
        public ?string $fallbackProvider = null,
        public ?string $fallbackModel = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'purpose' => ['required', 'string', 'in:extraction,drafting'],
            'provider' => ['required', 'string', 'max:32'],
            'model' => ['required', 'string', 'max:64'],
            'fallbackProvider' => ['nullable', 'string', 'max:32'],
            'fallbackModel' => ['nullable', 'string', 'max:64'],
        ];
    }
}
