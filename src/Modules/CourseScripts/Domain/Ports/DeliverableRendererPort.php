<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Ports;

use Modules\CourseScripts\Domain\ValueObjects\PracticeDocument;
use Modules\CourseScripts\Domain\ValueObjects\ScriptDocument;

/**
 * Renders deliverables from stored structure — never a generation (DEC-3).
 * Every method returns the file bytes keyed by format.
 */
interface DeliverableRendererPort
{
    /**
     * @return array{md: string, pdf: string}
     */
    public function script(ScriptDocument $document): array;

    /**
     * @return array{md: string, pdf: string}
     */
    public function promptsSheet(ScriptDocument $document): array;

    /**
     * @return array{md: string, pdf: string}
     */
    public function practice(PracticeDocument $document): array;

    /**
     * @return array{md: string, pdf: string}
     */
    public function practiceFile(PracticeDocument $document, string $fileName): array;
}
