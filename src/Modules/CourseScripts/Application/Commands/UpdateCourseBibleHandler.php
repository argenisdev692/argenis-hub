<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Modules\CourseScripts\Application\DTOs\CourseBibleData;
use Modules\CourseScripts\Domain\Enums\BibleOrigin;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Shared\Domain\Ports\AuditPort;

/**
 * The author accepts or edits the bible (US-3 · FR-9, FR-13). Returns how many
 * accepted scripts were written against an older revision.
 */
final readonly class UpdateCourseBibleHandler
{
    public function __construct(
        private CourseRepositoryPort $courses,
        private AuditPort $audit,
    ) {}

    /**
     * @return array{bible: CourseBibleData, bible_revision: int, stale_script_count: int}
     *
     * @throws CourseNotFoundException
     */
    public function handle(string $courseUuid, CourseBibleData $bible, int $userId, ?object $causer = null): array
    {
        $course = $this->courses->findOwned($courseUuid, $userId) ?? throw new CourseNotFoundException;

        $course = $this->courses->saveBible($course, $bible->toArray(), BibleOrigin::Author);

        $this->audit->log(
            'course_scripts.bible_updated',
            $course,
            ['course_uuid' => $course->uuid, 'bible_revision' => $course->bible_revision],
            $causer,
            'course_scripts.course',
        );

        return [
            'bible' => $bible,
            'bible_revision' => $course->bible_revision,
            'stale_script_count' => $this->courses->countScriptsBeforeBibleRevision($course),
        ];
    }
}
