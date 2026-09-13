<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Ports;

use Modules\CourseScripts\Domain\ValueObjects\ResearchBatch;
use Modules\CourseScripts\Domain\ValueObjects\ResearchFinding;

/**
 * Web research for scripts (US-4). Never throws: an outage yields an empty
 * batch and the video is written ungrounded (FR-13g).
 */
interface ResearchPort
{
    /**
     * @param  list<string>  $queries
     */
    public function search(array $queries, ?string $timeRange = null): ResearchBatch;

    /**
     * Full page content for a finding whose snippet is too thin (FR-13c).
     * Returns null when retrieval is disabled or fails.
     */
    public function fetchFullPage(ResearchFinding $finding): ?ResearchFinding;
}
