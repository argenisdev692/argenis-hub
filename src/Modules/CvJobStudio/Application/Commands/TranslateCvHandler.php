<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Application\DTOs\StudioCvVersionData;
use Modules\CvJobStudio\Domain\Exceptions\VersionNotFoundException;
use Modules\CvJobStudio\Domain\Ports\CvTranslatorPort;
use Modules\CvJobStudio\Domain\Ports\StudioCvRepositoryPort;

/**
 * ES / EN / PT-PT variants (T-078, FR-23) saved as child versions of the
 * source version. Prompt-cache layering lives in the `CvTranslatorPort`
 * adapter so three languages share one cached prefix.
 */
final readonly class TranslateCvHandler
{
    public function __construct(
        private CvTranslatorPort $translator,
        private StudioCvRepositoryPort $cvs,
    ) {}

    #[\NoDiscard]
    public function handle(string $versionUuid, string $language, int $userId): StudioCvVersionData
    {
        $version = $this->cvs->versionForUser($versionUuid, $userId)
            ?? throw new VersionNotFoundException("Version {$versionUuid} not found.");

        $sections = $this->translator->translate($version->content, $language, $userId);

        return $this->cvs->createVersion($version->childAttributes($userId, $language, $sections));
    }
}
