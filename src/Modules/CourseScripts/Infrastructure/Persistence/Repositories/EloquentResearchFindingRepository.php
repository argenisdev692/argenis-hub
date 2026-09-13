<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Repositories;

use Modules\CourseScripts\Domain\Ports\ResearchFindingRepositoryPort;
use Modules\CourseScripts\Domain\ValueObjects\ResearchFinding;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseResearchFindingEloquentModel;

final readonly class EloquentResearchFindingRepository implements ResearchFindingRepositoryPort
{
    public function save(int $courseId, ?int $videoId, array $findings): array
    {
        $stored = [];

        foreach ($findings as $finding) {
            $model = CourseResearchFindingEloquentModel::query()->create([
                'course_id' => $courseId,
                'course_video_id' => $videoId,
                'provider' => $finding->provider,
                'query' => $finding->query,
                'url' => $finding->url,
                'title' => $finding->title,
                'content' => $finding->content,
                'score' => $finding->score,
                'full_page_fetched' => $finding->fullPageFetched,
                'gathered_at' => now(),
            ]);

            $stored[] = self::toFinding($model);
        }

        return $stored;
    }

    public function forCourse(int $courseId): array
    {
        return CourseResearchFindingEloquentModel::query()
            ->where('course_id', $courseId)
            ->whereNull('course_video_id')
            ->orderByDesc('score')
            ->get()
            ->map(self::toFinding(...))
            ->values()
            ->all();
    }

    public function forVideo(int $videoId): array
    {
        return CourseResearchFindingEloquentModel::query()
            ->where('course_video_id', $videoId)
            ->orderByDesc('score')
            ->get()
            ->map(self::toFinding(...))
            ->values()
            ->all();
    }

    public function hasCourseFindings(int $courseId): bool
    {
        return CourseResearchFindingEloquentModel::query()->where('course_id', $courseId)->whereNull('course_video_id')->exists();
    }

    private static function toFinding(CourseResearchFindingEloquentModel $model): ResearchFinding
    {
        return new ResearchFinding(
            provider: $model->provider,
            query: $model->query,
            url: $model->url,
            title: $model->title,
            content: $model->content,
            score: $model->score,
            fullPageFetched: $model->full_page_fetched,
            id: $model->id,
        );
    }
}
