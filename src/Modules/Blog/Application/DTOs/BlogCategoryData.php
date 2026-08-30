<?php

declare(strict_types=1);

namespace Modules\Blog\Application\DTOs;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * Create/update payload for a blog category. Store and Update share ≥90% of the
 * fields and rules, so a single fused DTO is used (DTO Fusion Rule): `image` is
 * optional on both operations — on create it seeds the column, on update it only
 * replaces the object when a new file is uploaded.
 *
 * The image is validated as a real raster image (MIME + extension + magic bytes +
 * size + dimensions) before it reaches R2, then stored with public visibility so
 * blog pages can render it via a permanent URL (BACKEND-PHP §5 public-asset
 * exception — never used for private user uploads).
 */
final class BlogCategoryData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?UploadedFile $image = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $uuid = request()->route('uuid');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('blog_categories', 'blog_category_name')->ignore($uuid, 'uuid'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048', // 2 MB
                'dimensions:max_width=4096,max_height=4096',
            ],
        ];
    }
}
