<?php

declare(strict_types=1);

namespace Modules\Portfolios\Application\DTOs;

use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Public showcase entry for the landing page and the standalone Astro sites,
 * served over `GET /api/public/portfolios`.
 *
 * Deliberately narrower than {@see PortfolioData}: no owner id, no soft-delete
 * timestamp, no raw object keys and no `is_public` flag — nothing an
 * unauthenticated caller has any use for (OWASP §12 allowlist). Only rows that
 * are public AND already published ever reach this shape.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PublicPortfolioData extends Data
{
    /**
     * @param  list<string>  $techStack
     * @param  list<string>  $gallery
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $title,
        public readonly string $clientName,
        public readonly string $projectType,
        public readonly array $techStack,
        public readonly ?string $liveUrl,
        public readonly ?string $coverUrl,
        public readonly ?string $videoUrl,
        public readonly ?string $description,
        public readonly ?string $publishedAt,
        public readonly int $sortOrder,
        public readonly array $gallery,
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
            coverUrl: $portfolio->cover_url,
            videoUrl: $portfolio->video_url,
            description: $portfolio->description,
            publishedAt: $portfolio->published_at?->toIso8601String(),
            sortOrder: $portfolio->sort_order,
            gallery: $portfolio->media->map(static fn ($media): string => $media->url)->all(),
        );
    }
}
