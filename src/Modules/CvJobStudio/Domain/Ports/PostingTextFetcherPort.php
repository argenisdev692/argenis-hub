<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

interface PostingTextFetcherPort
{
    /**
     * @return array{text: string, completeness: string, cost_micros: int}|null null when not fetchable
     */
    public function fetch(string $url): ?array;

    public function stepName(): string;
}
