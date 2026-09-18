<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\ValueObjects;

use Modules\CvJobStudio\Domain\Enums\GateCode;

final readonly class GateVerdict
{
    public function __construct(
        public GateCode $gate,
        public bool $passed,
        public ?string $reasonCode = null,
        public ?string $detail = null,
    ) {}
}
