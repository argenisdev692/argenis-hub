<?php

declare(strict_types=1);

use Modules\Cvs\Domain\Enums\CvFileType;
use Modules\Cvs\Infrastructure\Services\CvTextExtractor;

function extractorPdfBytes(string $visibleText): string
{
    $pdf = "%PDF-1.4\n";
    $o = [];
    $o[1] = strlen($pdf);
    $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $o[2] = strlen($pdf);
    $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $o[3] = strlen($pdf);
    $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
    $stream = $visibleText === '' ? '' : "BT /F1 12 Tf 72 720 Td ({$visibleText}) Tj ET";
    $o[4] = strlen($pdf);
    $pdf .= '4 0 obj'."\n<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream\nendobj\n";
    $o[5] = strlen($pdf);
    $pdf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $x = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";

    foreach ([1, 2, 3, 4, 5] as $i) {
        $pdf .= sprintf('%010d 00000 n '."\n", $o[$i]);
    }

    return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$x."\n%%EOF";
}

function extractorTempFile(string $name, string $bytes): SplFileInfo
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.$name;
    file_put_contents($path, $bytes);

    return new SplFileInfo($path);
}

it('extracts text from a pdf through prinsfrank', function (): void {
    $file = extractorTempFile('cv-extractor-test.pdf', extractorPdfBytes('Hello PDF CV'));

    $text = app(CvTextExtractor::class)->extract(CvFileType::Pdf, $file);

    expect($text)->toContain('Hello PDF CV');

    @unlink($file->getPathname());
});

it('reports a scanned pdf as an extraction failure', function (): void {
    $file = extractorTempFile('cv-extractor-scanned.pdf', extractorPdfBytes(''));

    $text = app(CvTextExtractor::class)->extract(CvFileType::Pdf, $file);

    expect($text)->toBeNull();

    @unlink($file->getPathname());
});

it('still reads markdown as-is', function (): void {
    $file = extractorTempFile('cv-extractor-test.md', "# Fullstack Resume\n\nLaravel + Vue");

    $text = app(CvTextExtractor::class)->extract(CvFileType::Md, $file);

    expect($text)->toContain('Fullstack Resume');

    @unlink($file->getPathname());
});
