<?php

declare(strict_types=1);

namespace Modules\Cvs\Application\DTOs;

use Illuminate\Http\UploadedFile;
use Modules\Cvs\Domain\Enums\CvNiche;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Fused create/update input for a CV upload. Either a `file` or pasted
 * `content` (Markdown from the agent chat) is required on create — enforced
 * in CreateCvHandler so metadata-only updates keep working; both stay
 * optional on update to replace the stored file or leave it untouched.
 *
 * The response allowlist is {@see CvData} — this class never leaves the module.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class UploadCvData extends Data
{
    public function __construct(
        public string $title,
        public CvNiche $niche = CvNiche::Fullstack,
        public bool $isPrimary = false,
        public ?UploadedFile $file = null,
        public ?string $content = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'niche' => ['required', 'string', 'in:'.implode(',', CvNiche::values())],
            'is_primary' => ['boolean'],
            'file' => [
                'nullable',
                'file',
                'max:5120', // 5 MB
                'extensions:pdf,md,markdown',
                'mimetypes:text/markdown,text/plain,text/x-markdown,application/pdf',
            ],
            'content' => ['nullable', 'string', 'max:500000'],
        ];
    }

    /**
     * Normalized title — trimmed and clamped to the column width.
     */
    public function normalizedTitle(): string
    {
        return $this->title
            |> trim(...)
            |> (fn (string $title): string => mb_substr($title, 0, 255));
    }
}
