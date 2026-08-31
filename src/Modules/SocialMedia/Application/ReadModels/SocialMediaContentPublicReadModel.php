<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\ReadModels;

use Modules\SocialMedia\Application\Queries\GetPublicSocialMediaContentHandler;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Response shape for the anonymous social-media feed AND its single-package
 * detail view. Property-level authorization allowlist (OWASP §12 / API3): the
 * public JSON is built ONLY from these fields.
 *
 * What is deliberately NOT here is the point of the class. The five quality
 * scores, `eeat_analysis`, `optimization_suggestions`, `research_sources`,
 * `tavily_data_used`, `ai_detection_risk`, `iterations_required`,
 * `quality_warning*`, `provider`, `created_by` and the image PROMPTS are all
 * internal diagnostics — publishing them would hand any anonymous caller the
 * generation playbook, the provider in use, and an AI-detection self-report on
 * content the brand is presenting as its own.
 *
 * `body` and `platforms` stay null on the list endpoint (bandwidth — OWASP
 * API4) and are populated only by
 * {@see GetPublicSocialMediaContentHandler} for the detail view.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class SocialMediaContentPublicReadModel extends Data
{
    /**
     * @param  list<string>  $hashtags
     * @param  array<string, array{platform: string, adapted_content: string, hashtags: list<string>, image_url: string|null}>|null  $platforms
     */
    public function __construct(
        public string $uuid,
        public string $topic,
        public ?string $headline,
        public ?string $body,
        public ?string $callToAction,
        public array $hashtags,
        public ?string $coverImageUrl,
        public string $funnelStage,
        public string $language,
        public ?array $platforms,
        public ?string $publishedAt,
    ) {}

    public static function fromModel(SocialMediaContentEloquentModel $model, bool $includeBody = false): self
    {
        return new self(
            uuid: $model->uuid,
            topic: $model->topic,
            headline: $model->headline,
            body: $includeBody ? $model->body : null,
            callToAction: $model->call_to_action,
            hashtags: array_values($model->hashtags ?? []),
            coverImageUrl: $model->cover_image_url,
            funnelStage: $model->funnel_stage->value,
            language: $model->language->value,
            platforms: $includeBody ? self::publicPlatforms($model) : null,
            publishedAt: $model->published_at?->toIso8601String(),
        );
    }

    /**
     * Per-platform copy, stripped to what a public consumer can use. The stored
     * shape also carries `image_prompt`, `video_package` and voiceover paths —
     * production notes, not published content.
     *
     * @return array<string, array{platform: string, adapted_content: string, hashtags: list<string>, image_url: string|null}>
     */
    private static function publicPlatforms(SocialMediaContentEloquentModel $model): array
    {
        $platforms = [];

        foreach ($model->platforms ?? [] as $name => $variation) {
            if (! is_array($variation)) {
                continue;
            }

            $platforms[(string) $name] = [
                'platform' => (string) ($variation['platform'] ?? $name),
                'adapted_content' => (string) ($variation['adapted_content'] ?? ''),
                'hashtags' => array_values(array_map(strval(...), (array) ($variation['hashtags'] ?? []))),
                'image_url' => isset($variation['image_url']) ? (string) $variation['image_url'] : null,
            ];
        }

        return $platforms;
    }
}
