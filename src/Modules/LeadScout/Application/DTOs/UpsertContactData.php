<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Enums\RoleCategory;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Manual decisor upsert (spec US-11, plan §5 `UpsertContactData`).
 * Excluded/ambiguous titles are rejected in the handler (422); the email
 * must live on the company domain; opposed people answer 409.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class UpsertContactData extends Data
{
    public function __construct(
        public string $fullName,
        public string $roleTitle,
        public string $roleCategory,
        public ?string $publishedEmail = null,
        public ?string $publicProfileUrl = null,
        public ?bool $isPrimary = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:80'],
            'roleTitle' => ['required', 'string', 'max:120'],
            'roleCategory' => ['required', 'string', 'in:'.implode(',', RoleCategory::values())],
            'publishedEmail' => ['nullable', 'email:rfc', 'max:255'],
            'publicProfileUrl' => ['nullable', 'url', 'max:2048'],
            'isPrimary' => ['nullable', 'boolean'],
        ];
    }
}
