<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Ports;

use Modules\CourseScripts\Domain\ValueObjects\ResearchFinding;

/**
 * Persisted research: course-level findings are reused by every video (FR-13j).
 */
interface ResearchFindingRepositoryPort
{
    /**
     * @param  list<ResearchFinding>  $findings
     * @return list<ResearchFinding> the stored findings, with ids
     */
    public function save(int $courseId, ?int $videoId, array $findings): array;

    /**
     * @return list<ResearchFinding>
     */
    public function forCourse(int $courseId): array;

    /**
     * @return list<ResearchFinding>
     */
    public function forVideo(int $videoId): array;

    public function hasCourseFindings(int $courseId): bool;
}
