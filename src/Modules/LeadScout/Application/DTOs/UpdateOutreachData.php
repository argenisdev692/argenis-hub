<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Enums\OutreachChannel;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Outreach mutation (plan §5 `UpdateOutreachData`). `operator_id` is
 * deliberately absent: the server always stamps the authenticated user,
 * never client input (FR-41).
 */
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateOutreachData extends Data
{
    public function __construct(
        public ?string $draftBody = null,
        public ?string $stage = null,
        public ?string $notes = null,
        public ?string $contactChannelId = null,
        public ?string $sendMedium = null,
        public ?string $senderKind = null,
        public ?bool $acknowledgePendingLegal = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'draftBody' => ['nullable', 'string', 'max:10000'],
            'stage' => ['nullable', 'string', 'in:'.implode(',', OutreachStage::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
            'contactChannelId' => ['nullable', 'uuid'],
            'sendMedium' => ['nullable', 'string', 'in:'.implode(',', OutreachChannel::values())],
            'senderKind' => ['nullable', 'string', 'in:personal_mailbox,business_domain'],
            'acknowledgePendingLegal' => ['nullable', 'boolean'],
        ];
    }
}
