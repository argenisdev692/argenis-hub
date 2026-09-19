<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\LeadScout\Domain\Entities\Profile;
use Modules\LeadScout\Domain\Exceptions\CvNotFoundException;
use Modules\LeadScout\Domain\Exceptions\CvNotImportableException;
use Modules\LeadScout\Domain\Ports\CvSourcePort;
use Modules\LeadScout\Domain\Ports\ProfileRepositoryPort;
use Modules\LeadScout\Domain\Services\CvProfileParser;

/**
 * Imports a versioned matching profile from one of the operator's CVs
 * (spec US-1, FR-1). Deterministic, no LLM: the same CV always yields the
 * same profile. The CV text itself is never stored — only the derived
 * profile plus the source reference and content hash (staleness signal).
 */
final readonly class ImportCvProfileHandler
{
    public function __construct(
        private CvSourcePort $cvs,
        private CvProfileParser $parser,
        private ProfileRepositoryPort $profiles,
        private Config $config,
    ) {}

    public function handle(string $cvUuid, int $userId): Profile
    {
        $snapshot = $this->cvs->cvForUser($cvUuid, $userId) ?? throw new CvNotFoundException;

        if (! $snapshot->hasText()) {
            throw new CvNotImportableException('The CV has no extracted text (e.g. a scanned PDF).');
        }

        $parsed = $this->parser->parse(
            (string) $snapshot->rawText,
            (array) $this->config->get('lead-scout.skills.watch_list', []),
        );

        return $this->profiles->publishVersion(
            userId: $userId,
            sourceCvUuid: $snapshot->uuid,
            cvHash: $snapshot->contentHash,
            confirmedSkills: $parsed['confirmed'],
            potentialSkills: $parsed['potential'],
            proofPoints: $parsed['proofPoints'],
            languages: $parsed['languages'],
        );
    }
}
