<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;

/**
 * A normalized interval actually removed from the result, carrying the union of
 * the reasons and origins of every decision merged into it (FR-5).
 */
final readonly class AppliedCut
{
    /**
     * @param  list<CutReason>  $reasons
     * @param  list<DecisionOrigin>  $origins
     */
    public function __construct(
        public int $sequence,
        public TimeRange $range,
        public array $reasons,
        public array $origins,
    ) {}
}
