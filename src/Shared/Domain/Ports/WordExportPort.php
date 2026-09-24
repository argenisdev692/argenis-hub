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
     * Optional ATS keys: `contact` (`{name, lines}`) renders a centered
     * header first; a section `skills_line` renders as one keyword-dense
     * paragraph instead of bullets. Sections-only payloads render as before.
     *
     * @param  array{sections: list<array{heading: string, bullets?: list<string>, skills_line?: string}>, contact?: array{name: string, lines?: list<string>}}  $content
     */
    public function render(array $content): string;
}
