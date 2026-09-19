<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * ATS structural ruleset enforced at generation time (T-070, FR-6, SC-6):
 * single-column, table-free, image-free, standard headings, contact details
 * in the body (parsers skip headers/footers), standard font at 9–11pt,
 * MM/YYYY dates, at most 2 pages. Pure checks over a content snapshot so
 * DOCX and PDF both assert through it.
 */
final readonly class AtsStructureChecker
{
    /** @var list<string> */
    private const array STANDARD_HEADINGS = [
        'summary', 'experience', 'skills', 'education', 'languages', 'projects', 'certifications',
        'resumen', 'experiencia', 'habilidades', 'educación', 'educacion', 'idiomas', 'proyectos',
        'resumo', 'experiência', 'competências', 'educação',
    ];

    /**
     * @param  array{sections: list<array{heading: string}>, font: string, font_size_pt: float, page_count: int, has_tables: bool, has_images: bool, has_columns: bool, contact_in_body: bool, dates: list<string>}  $snapshot
     * @return array{passed: bool, checks: array<string, array{passed: bool, detail: string}>}
     */
    #[\NoDiscard]
    public function check(array $snapshot): array
    {
        $checks = [
            'single_column' => $this->result(! $snapshot['has_columns'], 'No multi-column layout.'),
            'table_free' => $this->result(! $snapshot['has_tables'], 'No tables.'),
            'image_free' => $this->result(! $snapshot['has_images'], 'No images.'),
            'contact_in_body' => $this->result($snapshot['contact_in_body'], 'Contact details in the document body.'),
            'standard_headings' => $this->checkHeadings($snapshot['sections']),
            'standard_font' => $this->result(
                in_array(mb_strtolower($snapshot['font']), ['arial', 'calibri', 'helvetica', 'georgia', 'garamond', 'dejavu sans'], true),
                "Font {$snapshot['font']}.",
            ),
            'font_size' => $this->result(
                $snapshot['font_size_pt'] >= 9.0 && $snapshot['font_size_pt'] <= 11.0,
                "Size {$snapshot['font_size_pt']}pt.",
            ),
            'date_format' => $this->checkDates($snapshot['dates']),
            'page_count' => $this->result($snapshot['page_count'] <= 2, "{$snapshot['page_count']} page(s)."),
        ];

        return [
            'passed' => array_reduce($checks, static fn (bool $carry, array $check): bool => $carry && $check['passed'], true),
            'checks' => $checks,
        ];
    }

    /** @return array{passed: bool, detail: string} */
    private function result(bool $passed, string $detail): array
    {
        return ['passed' => $passed, 'detail' => $detail];
    }

    /**
     * @param  list<array{heading: string}>  $sections
     * @return array{passed: bool, detail: string}
     */
    private function checkHeadings(array $sections): array
    {
        $nonStandard = [];

        foreach ($sections as $section) {
            if (! in_array(mb_strtolower(trim($section['heading'])), self::STANDARD_HEADINGS, true)) {
                $nonStandard[] = $section['heading'];
            }
        }

        return $this->result($nonStandard === [], $nonStandard === [] ? 'All headings standard.' : 'Non-standard: '.implode(', ', $nonStandard));
    }

    /**
     * @param  list<string>  $dates
     * @return array{passed: bool, detail: string}
     */
    private function checkDates(array $dates): array
    {
        $bad = array_filter($dates, static fn (string $date): bool => preg_match('/^\d{2}\/\d{4}$/', $date) !== 1);

        return $this->result($bad === [], $bad === [] ? 'All dates MM/YYYY.' : 'Non-MM/YYYY: '.implode(', ', $bad));
    }
}
