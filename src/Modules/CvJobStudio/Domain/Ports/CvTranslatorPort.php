<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

interface CvTranslatorPort
{
    /**
     * @param  array<array-key, mixed>  $content  stored CV version content
     * @return list<array<string, mixed>> translated sections
     */
    public function translate(array $content, string $language, int $userId): array;
}
