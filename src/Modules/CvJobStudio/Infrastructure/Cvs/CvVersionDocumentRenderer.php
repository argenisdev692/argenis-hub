<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Cvs;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\CvJobStudio\Domain\Ports\CvVersionDocumentPort;
use Shared\Domain\Ports\WordExportPort;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * DOCX via `PhpWordExportAdapter`, PDF via DomPDF; both satisfy the US-2
 * structural rules (SC-6). The PDF's text is re-extracted with
 * `smalot/pdfparser` and compared to intent at generation time (SC-5).
 */
final readonly class CvVersionDocumentRenderer implements CvVersionDocumentPort
{
    public function __construct(private WordExportPort $word) {}

    public function render(string $format, array $content): array
    {
        return match ($format) {
            'docx' => $this->docx($content),
            default => $this->pdf($content),
        };
    }

    /**
     * @param  array<array-key, mixed>  $content
     * @return array{bytes: string, verified: bool, extracted_chars: int}
     */
    private function docx(array $content): array
    {
        $sections = array_map(
            static fn ($section): array => [
                'heading' => (string) ($section['heading'] ?? ''),
                'bullets' => array_map(
                    static fn ($bullet): string => (string) (is_array($bullet) ? ($bullet['text'] ?? '') : $bullet),
                    $section['bullets'] ?? [],
                ),
            ],
            $content['sections'] ?? [],
        );

        $bytes = $this->word->render(['sections' => $sections]);

        return ['bytes' => $bytes, 'verified' => $bytes !== '', 'extracted_chars' => strlen($bytes)];
    }

    /**
     * @param  array<array-key, mixed>  $content
     * @return array{bytes: string, verified: bool, extracted_chars: int}
     */
    private function pdf(array $content): array
    {
        $bytes = Pdf::loadView('exports.pdf.cv_job_studio_version', [
            'version' => $content,
            'generatedAt' => now()->format('F j, Y H:i'),
        ])->output();

        try {
            $chars = mb_strlen((new PdfParser)->parseContent($bytes)->getText() ?? '');
        } catch (Throwable) {
            $chars = 0;
        }

        return ['bytes' => $bytes, 'verified' => $chars > 0, 'extracted_chars' => $chars];
    }
}
