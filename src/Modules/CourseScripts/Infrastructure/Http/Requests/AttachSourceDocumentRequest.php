<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Modules\CourseScripts\Domain\ValueObjects\IncomingDocument;

/**
 * One content file or style reference for an existing course (FR-1b, FR-10).
 */
final class AttachSourceDocumentRequest extends FormRequest
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
        return [
            'file' => ['required', ...StoreCourseRequest::fileRules()],
            'video_number' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toDocument(): IncomingDocument
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return StoreCourseRequest::document($file, $this->filled('video_number') ? $this->integer('video_number') : null);
    }
}
