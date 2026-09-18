<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

interface RequirementExtractorPort
{
    /**
     * Posting text is untrusted input: extraction runs in a constrained schema
     * and extracted values are validated against enums, never executed.
     *
     * @return array{requirements: list<array{canonical_name: string, raw_text: string, tag: string, nature: string}>, responsibilities: list<array{text: string, must_do: bool}>, provider: string, model: string, prompt_version: string}
     */
    public function extract(string $postingText): array;
}
