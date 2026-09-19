<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\Entities\AiSetting;
use Modules\LeadScout\Domain\Enums\AiPurpose;

interface AiSettingRepositoryPort
{
    public function forPurpose(AiPurpose $purpose): ?AiSetting;

    public function save(AiSetting $setting): void;
}
