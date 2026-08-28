<?php

declare(strict_types=1);

namespace Modules\Portfolios\Application\Commands;

use Modules\Portfolios\Application\DTOs\PortfolioData;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

final readonly class CreatePortfolioHandler
{
    use SyncsPortfolioMedia;

    /**
     * @param  array{
     *     title: string,
     *     client_name: string,
     *     project_type: string,
     *     tech_stack?: list<string>|null,
     *     live_url?: ?string,
     *     published_at?: ?string,
     *     is_public?: bool,
     *     cover_path?: ?string,
     *     video_path?: ?string,
     *     description?: ?string,
     *     sort_order?: int,
     *     media?: list<string>
     * }  $attributes
     */
    #[\NoDiscard('handle() returns the created portfolio.')]
    public function handle(array $attributes, int $userId): PortfolioData
    {
        $portfolio = PortfolioEloquentModel::query()->create([
            'user_id' => $userId,
            'title' => $attributes['title'],
            'client_name' => $attributes['client_name'],
            'project_type' => $attributes['project_type'],
            'tech_stack' => array_values($attributes['tech_stack'] ?? []),
            'live_url' => $attributes['live_url'] ?? null,
            'published_at' => $attributes['published_at'] ?? null,
            'is_public' => $attributes['is_public'] ?? true,
            'cover_path' => $attributes['cover_path'] ?? null,
            'video_path' => $attributes['video_path'] ?? null,
            'description' => $attributes['description'] ?? null,
            'sort_order' => $attributes['sort_order'] ?? 0,
        ]);

        if (array_key_exists('media', $attributes) && is_array($attributes['media'])) {
            $this->syncMedia($portfolio, $attributes['media']);
        }

        return PortfolioData::fromModel($portfolio->load('media'));
    }
}
