<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Modules\CourseScripts\Application\DTOs\UpdateVideoBriefData;
use Modules\CourseScripts\Application\DTOs\VideoBriefData;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;

/**
 * Stores the author's edit of a brief; the brief revision increments only when
 * something actually changed (FR-6, FR-31 anchor).
 */
final readonly class UpdateVideoBriefHandler
{
    private const int SUBSTANTIAL_NOTES_CHARS = 200;

    public function __construct(
        private CourseRepositoryPort $courses,
    ) {}

    /**
     * @throws CourseNotFoundException
     */
    public function handle(string $courseUuid, string $videoUuid, UpdateVideoBriefData $data, int $userId): VideoBriefData
    {
        $video = $this->courses->findOwnedVideo($courseUuid, $videoUuid, $userId) ?? throw new CourseNotFoundException;

        $attributes = $data->toAttributes();
        $isThin = $attributes['objective'] === null
            && $attributes['mandatory_content'] === []
            && $attributes['expected_result'] === null
            && $attributes['learning_areas'] === [];
        $attributes['needs_review'] = $isThin && mb_strlen((string) $attributes['notes']) < self::SUBSTANTIAL_NOTES_CHARS;

        $video = $this->courses->updateVideoBrief($video, $attributes);

        return VideoBriefData::fromModel($video, $video->course()->value('default_video_minutes') ?? 8);
    }
}
