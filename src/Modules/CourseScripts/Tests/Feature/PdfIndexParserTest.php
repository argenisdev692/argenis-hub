<?php

declare(strict_types=1);

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\CourseScripts\Domain\Exceptions\NoTextLayerException;
use Modules\CourseScripts\Domain\Exceptions\UnrecognisableIndexException;
use Modules\CourseScripts\Domain\Ports\IndexDocumentParserPort;
use Modules\CourseScripts\Tests\Support\IndexFixtures;

/**
 * The PDF ingestion path (spec FR-1, SC-2; clarify DEC-1).
 *
 * A Feature test rather than a Unit one because it resolves the port through
 * the container, so it also proves the service provider's wiring — the
 * dispatcher really does route `application/pdf` to the PDF adapter.
 */
it('parses a PDF index into the same structure as its markdown twin', function (): void {
    $parser = app(IndexDocumentParserPort::class);

    $fromMarkdown = $parser->parse(IndexFixtures::markdownPath(), 'text/markdown');
    $fromPdf = $parser->parse(IndexFixtures::pdfPath(), 'application/pdf');

    expect($fromPdf->groupCount())->toBe($fromMarkdown->groupCount())
        ->and($fromPdf->pointCount())->toBe($fromMarkdown->pointCount())
        ->and($fromPdf->title)->toBe($fromMarkdown->title)
        ->and($fromPdf->declaredTotalMinutes)->toBe($fromMarkdown->declaredTotalMinutes);
});

it('recovers at least 95 percent of brief fields from the PDF', function (): void {
    $parser = app(IndexDocumentParserPort::class);

    $markdownPoints = collect($parser->parse(IndexFixtures::markdownPath(), 'text/markdown')->points)
        ->keyBy('position');
    $pdfPoints = collect($parser->parse(IndexFixtures::pdfPath(), 'application/pdf')->points)
        ->keyBy('position');

    $compared = 0;
    $matched = 0;

    foreach ($markdownPoints as $number => $expected) {
        $actual = $pdfPoints->get($number);

        if ($actual === null) {
            $compared += 8;

            continue;
        }

        $pairs = [
            [$expected->title, $actual->title],
            [$expected->topic, $actual->topic],
            [$expected->groupNumber, $actual->groupNumber],
            [$expected->declaredDurationMinutes, $actual->declaredDurationMinutes],
            [$expected->objective, $actual->objective],
            [count($expected->learningAreas), count($actual->learningAreas)],
            [count($expected->mandatoryContent), count($actual->mandatoryContent)],
            [$expected->expectedResult, $actual->expectedResult],
        ];

        foreach ($pairs as [$a, $b]) {
            $compared++;
            $matched += $a === $b ? 1 : 0;
        }
    }

    $parity = $matched / max(1, $compared);

    // SC-2: the PDF path must reproduce the Markdown parse for >= 95% of fields.
    expect($parity)->toBeGreaterThanOrEqual(0.95, sprintf('parity %.1f%% (%d/%d)', $parity * 100, $matched, $compared));
});

it('rejects a PDF with no text layer instead of producing an empty course', function (): void {
    // A raster print-to-PDF has no extractable text and no OCR exists, so the
    // only honest outcome is a refusal that names the cause (research R1.3).
    $path = tempnam(sys_get_temp_dir(), 'cs-').'.pdf';

    $pdf = Pdf::loadHTML(
        '<html><head><meta charset="utf-8"></head><body><div style="width:10px;height:10px"></div></body></html>'
    );
    file_put_contents($path, $pdf->output());

    expect(fn () => app(IndexDocumentParserPort::class)->parse($path, 'application/pdf'))
        ->toThrow(NoTextLayerException::class);

    @unlink($path);
});

it('rejects an unsupported file type', function (): void {
    expect(fn () => app(IndexDocumentParserPort::class)->parse(IndexFixtures::markdownPath(), 'image/png'))
        ->toThrow(UnrecognisableIndexException::class);
});

it('routes markdown and pdf mime types to a supporting parser', function (): void {
    $parser = app(IndexDocumentParserPort::class);

    expect($parser->supports('text/markdown'))->toBeTrue()
        ->and($parser->supports('text/plain'))->toBeTrue()
        ->and($parser->supports('application/pdf'))->toBeTrue()
        ->and($parser->supports('image/png'))->toBeFalse()
        ->and($parser->supports('application/zip'))->toBeFalse();
});
