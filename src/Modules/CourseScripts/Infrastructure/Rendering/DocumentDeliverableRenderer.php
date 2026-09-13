<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Rendering;

use Modules\CourseScripts\Domain\Ports\DeliverableRendererPort;
use Modules\CourseScripts\Domain\ValueObjects\PracticeDocument;
use Modules\CourseScripts\Domain\ValueObjects\ScriptDocument;
use Shared\Domain\Ports\ExportPort;

/**
 * Markdown from {@see MarkdownDocumentRenderer}, PDF from the shared export
 * port over the module's Blade templates (research R5.2 — no new PDF adapter).
 */
final readonly class DocumentDeliverableRenderer implements DeliverableRendererPort
{
    public function __construct(
        private MarkdownDocumentRenderer $markdown,
        private ExportPort $export,
    ) {}

    public function script(ScriptDocument $document): array
    {
        return [
            'md' => $this->markdown->script($document),
            'pdf' => $this->pdf($document->fileName, 'exports.pdf.course-scripts.script', $document->language, ['document' => $document]),
        ];
    }

    public function promptsSheet(ScriptDocument $document): array
    {
        return [
            'md' => $this->markdown->promptsSheet($document),
            'pdf' => $this->pdf($document->promptsFileName, 'exports.pdf.course-scripts.prompts', $document->language, ['document' => $document]),
        ];
    }

    public function practice(PracticeDocument $document): array
    {
        return [
            'md' => $this->markdown->practice($document),
            'pdf' => $this->pdf($document->documentName, 'exports.pdf.course-scripts.practice', $document->language, ['document' => $document]),
        ];
    }

    public function practiceFile(PracticeDocument $document, string $fileName): array
    {
        return [
            'md' => $this->markdown->practiceFile($document, $fileName),
            'pdf' => $this->pdf($fileName, 'exports.pdf.course-scripts.practice-file', $document->language, [
                'artifact' => $document->artifact($fileName) ?? ['file_name' => $fileName, 'content_blocks' => []],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pdf(string $name, string $view, string $language, array $data): string
    {
        return (string) $this->export
            ->pdf($name.'.pdf', $view, [...$data, 't' => RenderVocabulary::for($language), 'language' => $language], 'a4', 'portrait')
            ->getContent();
    }
}
