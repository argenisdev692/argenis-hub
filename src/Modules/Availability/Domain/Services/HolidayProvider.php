<?php

declare(strict_types=1);

namespace Modules\Availability\Domain\Services;

interface HolidayProvider
{
    public function countryCode(): string;

    /**
     * @return array<string, string>
     */
    public function forYear(int $year): array;
}
