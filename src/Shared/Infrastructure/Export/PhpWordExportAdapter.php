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
 */
final readonly class PhpWordExportAdapter implements WordExportPort
{
    public function render(array $content): string
    {
        $document = new PhpWord;
        $document->setDefaultFontName('Calibri');
        $document->setDefaultFontSize(10);

        $section = $document->addSection();

        foreach ($content['sections'] as $sectionData) {
            $section->addTitle($sectionData['heading'], 1);

            foreach ($sectionData['bullets'] as $bullet) {
                $section->addListItem($bullet, 0, null, null, ['alignment' => Jc::LEFT]);
            }
        }

        $temporary = tempnam(sys_get_temp_dir(), 'studio-docx').'.docx';
        $document->save($temporary, 'Word2007');

        $bytes = (string) file_get_contents($temporary);
        @unlink($temporary);

        return $bytes;
    }
}
