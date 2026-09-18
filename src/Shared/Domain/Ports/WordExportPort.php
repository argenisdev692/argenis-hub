<?php

declare(strict_types=1);

namespace Shared\Domain\Ports;

/**
 * Word-document generation contract (CvJobStudio T-074). Kept separate from
 * `ExportPort` (ISP): the CSV/XLSX/PDF adapters must not gain a DOCX surface
 * they never implement. Local disk is used only for ephemeral rendering; the
 * final bytes go to R2 through `StoragePort` by the caller.
 */
interface WordExportPort
{
    /**
     * @param  array{sections: list<array{heading: string, bullets: list<string>}>}  $content
     */
    public function render(array $content): string;
}
