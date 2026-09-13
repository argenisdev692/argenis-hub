<?php

declare(strict_types=1);

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\CourseScripts\Domain\Exceptions\NoTextLayerException;
use Modules\CourseScripts\Domain\Exceptions\UnrecognisableIndexException;
use Modules\CourseScripts\Domain\Ports\DocumentTextExtractorPort;
use Modules\CourseScripts\Tests\Support\IndexFixtures;

/**
 * Content files (FR-1b): plain text, no structure, same PDF diagnostics as an
 * index.
 */
it('reads a markdown content file verbatim', function (): void {
    $text = app(DocumentTextExtractorPort::class)->extract(IndexFixtures::path('content-notes.md'), 'text/markdown');

    expect($text)->toContain('BUSCARX busca en cualquier dirección')
        ->and($text)->toContain('## Tablas dinámicas');
});

it('extracts the text layer of a PDF content file', function (): void {
    $path = IndexFixtures::pdfTwinOf('content-notes.md');

    try {
        $text = app(DocumentTextExtractorPort::class)->extract($path, 'application/pdf');
    } finally {
        @unlink($path);
    }

    expect($text)->toContain('BUSCARX')
        ->and($text)->toContain('segmentación');
});

it('converts a Windows-1252 text file to UTF-8', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'cs-1252-');
    file_put_contents($path, mb_convert_encoding('Guion de práctica: gestión', 'Windows-1252', 'UTF-8'));

    try {
        $text = app(DocumentTextExtractorPort::class)->extract($path, 'text/plain');
    } finally {
        @unlink($path);
    }

    expect($text)->toBe('Guion de práctica: gestión');
});

it('rejects a scanned PDF content file with the no-text-layer diagnosis', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'cs-scan-').'.pdf';
    file_put_contents($path, Pdf::loadHTML('<html><body><div style="width:5px;height:5px"></div></body></html>')->output());

    try {
        expect(fn () => app(DocumentTextExtractorPort::class)->extract($path, 'application/pdf'))
            ->toThrow(NoTextLayerException::class);
    } finally {
        @unlink($path);
    }
});

it('rejects an unsupported content type', function (): void {
    expect(fn () => app(DocumentTextExtractorPort::class)->extract(IndexFixtures::markdownPath(), 'application/zip'))
        ->toThrow(UnrecognisableIndexException::class);
});
