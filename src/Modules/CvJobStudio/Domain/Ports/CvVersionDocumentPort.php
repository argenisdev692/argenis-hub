<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

interface CvVersionDocumentPort
{
    /**
     * Renders a CV version to a file and verifies its text is machine
     * readable (SC-5): a PDF's text is re-extracted and measured.
     *
     * @param  'docx'|'pdf'  $format
     * @param  array<array-key, mixed>  $content
     * @return array{bytes: string, verified: bool, extracted_chars: int}
     */
    public function render(string $format, array $content): array;
}
