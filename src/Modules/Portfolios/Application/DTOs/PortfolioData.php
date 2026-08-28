<?php

declare(strict_types=1);

namespace Modules\Portfolios\Application\DTOs;

use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Admin-facing representation of a portfolio project — the allowlist behind
 * every `/data/admin/portfolios` response. The auto-increment `id` and the
 * owning `user_id` never cross this boundary (OWASP §12).
 *
 * `mediaPaths` carries the raw R2 object keys so the admin edit form can
 * round-trip the gallery; `gallery` carries the resolved absolute URLs for
 * preview. `coverUrl` / `videoUrl` are resolved the same way.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PortfolioData extends Data
{
    /**
     * @param  list<string>  $techStack
     * @param  list<string>  $mediaPaths
     * @param  list<string>  $gallery
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $title,
        public readonly string $clientName,
        public readonly string $projectType,
        public readonly array $techStack,
        public readonly ?string $liveUrl,
        public readonly ?string $publishedAt,
        public readonly bool $isPublic,
        public readonly ?string $coverPath,
        public readonly ?string $coverUrl,
        public readonly ?string $videoPath,
        public readonly ?string $videoUrl,
        public readonly ?string $description,
        public readonly int $sortOrder,
        public readonly array $mediaPaths,
        public readonly array $gallery,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly ?string $deletedAt,
    ) {}

    public static function fromModel(PortfolioEloquentModel $portfolio): self
    {
        return new self(
            uuid: $portfolio->uuid,
            title: $portfolio->title,
            clientName: $portfolio->client_name,
            projectType: $portfolio->project_type,
            techStack: array_values($portfolio->tech_stack ?? []),
            liveUrl: $portfolio->live_url,
            publishedAt: $portfolio->published_at?->toIso8601String(),
            isPublic: $portfolio->is_public,
            coverPath: $portfolio->cover_path,
            coverUrl: $portfolio->cover_url,
            videoPath: $portfolio->video_path,
            videoUrl: $portfolio->video_url,
            description: $portfolio->description,
            sortOrder: $portfolio->sort_order,
            mediaPaths: $portfolio->media->pluck('path')->all(),
            gallery: $portfolio->media->map(static fn ($media): string => $media->url)->all(),
            createdAt: $portfolio->created_at?->toIso8601String(),
            updatedAt: $portfolio->updated_at?->toIso8601String(),
            deletedAt: $portfolio->deleted_at?->toIso8601String(),
        );
    }
}
