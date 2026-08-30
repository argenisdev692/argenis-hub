<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Create/update payload for a blog post (DTO Fusion Rule — store and update
 * share the same shape). `coverImage` (an uploaded file) and `coverImagePath`
 * (an already-stored R2 key, produced by the AI cover-image generation step)
 * are mutually exclusive: the handler prefers a fresh upload over the
 * AI-generated path when both are present. The `ai*` fields are write-once
 * metadata echoed back from `GeneratePostContentHandler` — never user-typed —
 * so a manually written post simply omits them (defaults stay null/false).
 */
#[MapInputName(SnakeCaseMapper::class)]
final class PostData extends Data
{
    public function __construct(
        public string $title,
        public string $content,
        public ?string $excerpt = null,
        public ?UploadedFile $coverImage = null,
        public ?string $coverImagePath = null,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?string $metaKeywords = null,
        public ?string $categoryUuid = null,
        public string $status = 'draft',
        public ?string $scheduledAt = null,
        public bool $isAiGenerated = false,
        public ?string $aiProvider = null,
        public ?int $seoScore = null,
        public ?int $eeatScore = null,
        public ?int $humanWritingIndex = null,
        public ?int $aiDetectionRisk = null,
        /** @var array<string, mixed>|null */
        public ?array $aiScores = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $uuid = request()->route('uuid');

        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'cover_image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:4096', // 4 MB
                'dimensions:max_width=4096,max_height=4096',
            ],
            'cover_image_path' => ['nullable', 'string', 'max:2048'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'category_uuid' => ['nullable', 'uuid', Rule::exists('blog_categories', 'uuid')],
            'status' => ['required', 'string', Rule::in(['draft', 'published', 'scheduled'])],
            'scheduled_at' => ['nullable', 'date', 'after:now', 'required_if:status,scheduled'],
            'is_ai_generated' => ['boolean'],
            'ai_provider' => ['nullable', 'string', Rule::in(['openai', 'anthropic', 'gemini'])],
            'seo_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'eeat_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'human_writing_index' => ['nullable', 'integer', 'min:0', 'max:100'],
            'ai_detection_risk' => ['nullable', 'integer', 'min:0', 'max:100'],
            'ai_scores' => ['nullable', 'array'],
        ];
    }
}
