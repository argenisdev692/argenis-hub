<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Entities;

use Modules\LeadScout\Domain\Enums\AiPurpose;

/**
 * Stored default + fallback provider/model for one AI purpose (spec US-10,
 * FR-22). Never holds secrets.
 */
final readonly class AiSetting
{
    public function __construct(
        public AiPurpose $purpose,
        public ?string $provider,
        public ?string $model,
        public ?string $fallbackProvider,
        public ?string $fallbackModel,
    ) {}
}
