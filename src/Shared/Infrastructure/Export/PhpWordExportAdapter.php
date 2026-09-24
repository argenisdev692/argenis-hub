<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Export;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use Shared\Domain\Ports\WordExportPort;

/**
 * First consumer of `phpoffice/phpword` in this codebase (T-074, RK-7):
 * single-column, table-free, standard font at 10pt, standard headings —
 * satisfying the same structural ruleset the checker asserts (SC-6).
 *
 * Optional ATS keys (absent = previous behaviour): `contact` renders a
 * centered name + contact lines first; a section `skills_line` renders as a
 * single keyword-dense paragraph instead of a bullet list.
 */
final readonly class PhpWordExportAdapter implements WordExportPort
{
    public function render(array $content): string
    {
        $document = new PhpWord;
        $document->setDefaultFontName('Calibri');
        $document->setDefaultFontSize(10);

        $section = $document->addSection();

        $contact = $content['contact'] ?? null;

        if (is_array($contact) && trim((string) ($contact['name'] ?? '')) !== '') {
            $section->addText(trim((string) $contact['name']), ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);

            foreach ((array) ($contact['lines'] ?? []) as $line) {
                $line = trim((string) $line);

                if ($line !== '') {
                    $section->addText($line, ['size' => 9], ['alignment' => Jc::CENTER]);
                }
            }
        }

        foreach ($content['sections'] as $sectionData) {
            $section->addTitle((string) ($sectionData['heading'] ?? ''), 1);

            $skillsLine = trim((string) ($sectionData['skills_line'] ?? ''));

            if ($skillsLine !== '') {
                $section->addText($skillsLine);
            }

            foreach ((array) ($sectionData['bullets'] ?? []) as $bullet) {
                $text = trim((string) (is_array($bullet) ? ($bullet['text'] ?? '') : $bullet));

                if ($text !== '') {
                    $section->addListItem($text, 0, null, null, ['alignment' => Jc::LEFT]);
                }
            }
        }

        $temporary = tempnam(sys_get_temp_dir(), 'studio-docx').'.docx';
        $document->save($temporary, 'Word2007');

        $bytes = (string) file_get_contents($temporary);
        @unlink($temporary);

        return $bytes;
    }
}
