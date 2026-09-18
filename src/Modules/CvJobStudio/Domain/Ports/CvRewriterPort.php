<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

interface CvRewriterPort
{
    /**
     * @param  array<string, mixed>  $structure  confirmed CV structure snapshot
     * @param  array<string, mixed>  $protectedBlock
     * @return array{sections: list<array{heading: string, bullets: list<array{text: string, source_bullet: string|null}>}>, cut_notes: list<string>, provider: string, model: string}
     */
    public function rewrite(array $structure, array $protectedBlock, string $language): array;
}
