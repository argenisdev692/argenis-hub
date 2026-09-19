<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

interface CvStructureParserPort
{
    /**
     * @return array{profile_facts: array<string, mixed>, entries: list<array<string, mixed>>, bullets: list<array<string, mixed>>, skills: list<array<string, mixed>>, provider: string, model: string, parser_version: string}
     */
    public function parse(string $rawText, int $userId): array;
}
