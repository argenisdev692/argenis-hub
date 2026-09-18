<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

interface EmbeddingPort
{
    /**
     * @param  list<string>  $texts
     * @return array{vectors: list<list<float>>, model: string, dims: int}
     */
    public function embed(array $texts): array;
}
