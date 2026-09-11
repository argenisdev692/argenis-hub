<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use Modules\VideoEdits\Domain\Enums\VideoEditMode;

/**
 * Everything a cut-decision producer may look at: the requested mode and
 * parameters plus the merged working file (EX-2). V2 speech producers and the V3
 * AI producer read the same context; they never touch persistence or rendering.
 */
final readonly class DecisionContext
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public VideoEditMode $mode,
        public array $parameters,
        public string $workingPath,
        public MediaProbe $workingProbe,
    ) {}
}
