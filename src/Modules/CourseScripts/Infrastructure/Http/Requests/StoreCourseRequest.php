<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Modules\CourseScripts\Application\DTOs\StoreCourseInput;
use Modules\CourseScripts\Domain\ValueObjects\IncomingDocument;

/**
 * Title + index + optional content files (US-1 · FR-1, FR-1b, FR-58).
 *
 * `mimetypes` checks the real bytes via fileinfo; `extensions` checks the
 * client name. Both must pass.
 */
final class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $file = $this->fileRules();

        return [
            'title' => ['nullable', 'string', 'max:'.(int) config('course-scripts.structure.max_title_length', 255)],
            'index' => ['required', ...$file],
            'contents' => ['nullable', 'array', 'max:'.(int) config('course-scripts.uploads.max_content_files', 10)],
            'contents.*' => $file,
            'content_video_numbers' => ['nullable', 'array'],
            'content_video_numbers.*' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toInput(): StoreCourseInput
    {
        /** @var UploadedFile $index */
        $index = $this->file('index');
        $numbers = (array) $this->input('content_video_numbers', []);

        $contents = [];

        foreach (array_values((array) $this->file('contents', [])) as $position => $content) {
            $contents[] = self::document($content, isset($numbers[$position]) && $numbers[$position] !== null && $numbers[$position] !== '' ? (int) $numbers[$position] : null);
        }

        return new StoreCourseInput(
            title: $this->filled('title') ? (string) $this->input('title') : null,
            index: self::document($index),
            contents: $contents,
        );
    }

    public static function document(UploadedFile $file, ?int $videoNumber = null): IncomingDocument
    {
        return new IncomingDocument(
            localPath: (string) $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
            mimeType: (string) $file->getMimeType(),
            sizeBytes: (int) $file->getSize(),
            videoNumber: $videoNumber,
        );
    }

    /**
     * @return list<string>
     */
    public static function fileRules(): array
    {
        return [
            'file',
            'max:'.(int) config('course-scripts.uploads.max_kb'),
            'extensions:'.implode(',', (array) config('course-scripts.uploads.allowed_extensions')),
            'mimetypes:'.implode(',', (array) config('course-scripts.uploads.allowed_mime_types')),
        ];
    }
}
