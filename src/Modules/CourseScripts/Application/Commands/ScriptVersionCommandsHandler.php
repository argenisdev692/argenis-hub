<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Modules\CourseScripts\Application\DTOs\RegenerateScriptData;
use Modules\CourseScripts\Domain\Enums\GenerationRunKind;
use Modules\CourseScripts\Domain\Enums\GenerationScope;
use Modules\CourseScripts\Domain\Enums\VideoScriptStatus;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Exceptions\RunInProgressException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Domain\Ports\ScriptVersionRepositoryPort;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseGenerationRunEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseScriptVersionEloquentModel;
use Shared\Domain\Ports\AuditPort;

/**
 * Regenerate with feedback, force a practice pack, accept a version
 * (US-14, US-7 · FR-38, FR-49, FR-50, FR-34).
 */
final readonly class ScriptVersionCommandsHandler
{
    public function __construct(
        private CourseRepositoryPort $courses,
        private ScriptVersionRepositoryPort $versions,
        private StartGenerationRunHandler $start,
        private BuildDeliverablesHandler $deliverables,
        private AuditPort $audit,
    ) {}

    /**
     * @throws CourseNotFoundException
     * @throws RunInProgressException
     */
    public function regenerate(string $courseUuid, string $videoUuid, RegenerateScriptData $data, int $userId, bool $forcePractice = false, ?object $causer = null): CourseGenerationRunEloquentModel
    {
        $video = $this->courses->findOwnedVideo($courseUuid, $videoUuid, $userId) ?? throw new CourseNotFoundException;
        $course = $this->courses->findById($video->course_id) ?? throw new CourseNotFoundException;

        return $this->start->start(
            course: $course,
            videoIds: [$video->id],
            writerProvider: $data->writerProvider,
            withReview: $data->reviewRequested(),
            scope: GenerationScope::Selection->value,
            blockId: null,
            userId: $userId,
            estimate: ['ai_write_calls' => 0, 'ai_review_calls' => 0, 'research_calls' => 0],
            kind: $forcePractice ? GenerationRunKind::ForcePractice : GenerationRunKind::Regeneration,
            causer: $causer,
            feedbackNote: $data->feedbackNote,
        );
    }

    /**
     * Makes a version the accepted one, rebuilds its files and flags later
     * scripts whose continuity used this video (flag only, D9).
     *
     * @throws CourseNotFoundException
     */
    public function accept(string $courseUuid, string $videoUuid, string $versionUuid, int $userId, ?object $causer = null): CourseScriptVersionEloquentModel
    {
        $version = $this->versions->findOwned($courseUuid, $videoUuid, $userId, $versionUuid) ?? throw new CourseNotFoundException;

        $version = $this->versions->accept($version);
        $this->deliverables->handle($version);

        $courseId = (int) $version->video->course_id;
        $stale = $this->versions->markContinuityStale($courseId, $version->course_video_id);
        $this->courses->updateVideoStatus($version->course_video_id, VideoScriptStatus::Generated);
        $this->courses->refreshStatus($courseId);

        $this->audit->log('course_scripts.version_accepted', $version, [
            'version_uuid' => $version->uuid,
            'version' => $version->version,
            'stale_scripts_flagged' => $stale,
        ], $causer, 'course_scripts.course');

        return $version;
    }
}
