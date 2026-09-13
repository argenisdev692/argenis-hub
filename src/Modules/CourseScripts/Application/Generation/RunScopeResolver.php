<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Generation;

use Modules\CourseScripts\Application\DTOs\EstimateRunData;
use Modules\CourseScripts\Domain\Enums\GenerationScope;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Exceptions\RunRequestRejectedException;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseBlockEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoEloquentModel;

/**
 * Which videos a run covers, in course order (FR-15, FR-17). UUIDs from the
 * request are matched against the course's own videos only, so a foreign id
 * simply does not exist here.
 */
final readonly class RunScopeResolver
{
    /**
     * @return array{videos: list<CourseVideoEloquentModel>, block_id: ?int}
     *
     * @throws RunRequestRejectedException
     * @throws CourseNotFoundException
     */
    public function resolve(CourseEloquentModel $course, EstimateRunData $data): array
    {
        $course->loadMissing([
            'blocks' => static fn ($query) => $query->select(['id', 'uuid', 'course_id']),
            'videos' => static fn ($query) => $query->select(['id', 'uuid', 'course_id', 'course_block_id', 'number', 'declared_duration_minutes'])->orderBy('number'),
        ]);

        $blockId = null;

        $videos = match ($data->scope) {
            GenerationScope::Course => $course->videos,
            GenerationScope::Block => (function () use ($course, $data, &$blockId) {
                $block = $course->blocks->first(static fn (CourseBlockEloquentModel $block): bool => $block->uuid === $data->blockUuid) ?? throw new CourseNotFoundException;
                $blockId = $block->id;

                return $course->videos->where('course_block_id', $block->id);
            })(),
            GenerationScope::Selection => $course->videos->whereIn('uuid', (array) $data->videoUuids),
        };

        $videos = $videos->sortBy('number')->values()->all();

        if ($videos === []) {
            throw RunRequestRejectedException::emptyScope();
        }

        return ['videos' => $videos, 'block_id' => $blockId];
    }
}
