<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

interface SimilaritySearchPort
{
    /**
     * @param  list<float>  $vector
     * @return list<array{owner_id: int, distance: float}>
     */
    public function nearest(string $ownerType, int $userId, array $vector, int $limit): array;
}
