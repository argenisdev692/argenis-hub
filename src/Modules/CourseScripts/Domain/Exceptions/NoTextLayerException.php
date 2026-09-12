<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Exceptions;

use RuntimeException;

/**
 * The PDF carries no extractable text — it is a scan or a raster print.
 *
 * This is not hypothetical: `CLAUDE-PARA-USUARIOS-RESUMEN.pdf` in the project's
 * own reference folder is 43 pages produced by "Microsoft: Print To PDF" and
 * yields one character per page.
 *
 * There is no OCR in scope (research R1.3), so the honest outcome is a refusal
 * that names the cause, letting the author upload the Markdown source instead.
 */
final class NoTextLayerException extends RuntimeException
{
    public const string CODE = 'no_text_layer';

    public function __construct(public readonly int $extractedCharacters)
    {
        parent::__construct(
            'This PDF contains no selectable text (it looks like a scan or a print-to-PDF). '
            .'Upload the Markdown source, or a PDF exported with a text layer.'
        );
    }
}
