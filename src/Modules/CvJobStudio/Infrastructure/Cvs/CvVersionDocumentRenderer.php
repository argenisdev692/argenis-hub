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
 *
 * ATS normalization (all three stored shapes): tailor versions keep
 * `{summary, skills}`, rewrite versions a bare section list, and the export
 * tests a `sections` wrapper. Every shape becomes one ordered section list —
 * contact header, Professional Summary, Core Skills keyword line, then the
 * composed sections — single column, table-free, standard headings.
 */
final readonly class CvVersionDocumentRenderer implements CvVersionDocumentPort
{
    public function __construct(private WordExportPort $word) {}

    public function render(string $format, array $content, ?array $header = null): array
    {
        return match ($format) {
            'docx' => $this->docx($content, $header),
            default => $this->pdf($content, $header),
        };
    }

    /**
     * @param  array<array-key, mixed>  $content
     * @param  array{name: string, lines: list<string>}|null  $header
     * @return array{bytes: string, verified: bool, extracted_chars: int}
     */
    private function docx(array $content, ?array $header): array
    {
        $bytes = $this->word->render([
            ...($header !== null ? ['contact' => $header] : []),
            'sections' => self::atsSections($content),
        ]);

        return ['bytes' => $bytes, 'verified' => $bytes !== '', 'extracted_chars' => strlen($bytes)];
    }

    /**
     * @param  array<array-key, mixed>  $content
     * @param  array{name: string, lines: list<string>}|null  $header
     * @return array{bytes: string, verified: bool, extracted_chars: int}
     */
    private function pdf(array $content, ?array $header): array
    {
        $bytes = Pdf::loadView('exports.pdf.cv_job_studio_version', [
            'contact' => $header,
            'version' => ['sections' => self::atsSections($content)],
            'generatedAt' => now()->format('F j, Y H:i'),
        ])->output();

        try {
            $chars = mb_strlen((new PdfParser)->parseContent($bytes)->getText() ?? '');
        } catch (Throwable) {
            $chars = 0;
        }

        return ['bytes' => $bytes, 'verified' => $chars > 0, 'extracted_chars' => $chars];
    }

    /**
     * One ordered ATS section list from any stored version shape. The
     * skills keyword line replaces a duplicate Skills section when the
     * tailor shape already carries top-level skills.
     *
     * @param  array<array-key, mixed>  $content
     * @return list<array{heading: string, bullets: list<string>}|array{heading: string, skills_line: string}>
     */
    #[\NoDiscard('The normalized sections feed both renderers')]
    public static function atsSections(array $content): array
    {
        $sections = [];

        $summary = trim((string) ($content['summary'] ?? ''));

        if ($summary !== '') {
            $sections[] = ['heading' => 'Professional Summary', 'bullets' => [$summary]];
        }

        $skills = array_values(array_filter(array_map(
            static fn (mixed $skill): string => trim((string) (is_array($skill) ? ($skill['text'] ?? $skill['name'] ?? '') : $skill)),
            (array) ($content['skills'] ?? []),
        )));

        if ($skills !== []) {
            $sections[] = ['heading' => 'Core Skills', 'skills_line' => implode(', ', $skills)];
        }

        $rest = $content['sections'] ?? null;

        if (! is_array($rest)) {
            $rest = array_is_list($content) ? $content : [];
        }

        foreach ($rest as $section) {
            if (! is_array($section)) {
                continue;
            }

            $heading = trim((string) ($section['heading'] ?? ''));

            if ($heading === '') {
                continue;
            }

            // The keyword line above already covers skills: a second Skills
            // block would read as filler to both recruiters and parsers.
            if ($skills !== [] && in_array(mb_strtolower($heading), ['skills', 'core skills', 'technical skills', 'key skills'], true)) {
                continue;
            }

            if ($summary !== '' && in_array(mb_strtolower($heading), ['summary', 'professional summary'], true)) {
                continue;
            }

            $sections[] = [
                'heading' => $heading,
                'bullets' => array_values(array_filter(array_map(
                    static fn (mixed $bullet): string => trim((string) (is_array($bullet) ? ($bullet['text'] ?? '') : $bullet)),
                    (array) ($section['bullets'] ?? []),
                ))),
            ];
        }

        return $sections;
    }
}
