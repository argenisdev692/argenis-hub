<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Domain\Exceptions\CvNotFoundException;
use Modules\LeadScout\Domain\Exceptions\CvNotImportableException;
use Modules\LeadScout\Domain\Ports\CvSourcePort;
use Modules\LeadScout\Domain\Services\CvProfileParser;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutProfileEloquentModel;

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
    ) {}

    public function handle(string $cvUuid, int $userId): ScoutProfileEloquentModel
    {
        $snapshot = $this->cvs->cvForUser($cvUuid, $userId) ?? throw new CvNotFoundException;

        if (! $snapshot->hasText()) {
            throw new CvNotImportableException('The CV has no extracted text (e.g. a scanned PDF).');
        }

        $parsed = $this->parser->parse(
            (string) $snapshot->rawText,
            (array) config('lead-scout.skills.watch_list', []),
        );

        return DB::transaction(function () use ($snapshot, $userId, $parsed): ScoutProfileEloquentModel {
            ScoutProfileEloquentModel::query()
                ->where('user_id', $userId)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $version = (int) (ScoutProfileEloquentModel::query()->where('user_id', $userId)->max('version') ?? 0) + 1;

            return ScoutProfileEloquentModel::query()->create([
                'user_id' => $userId,
                'version' => $version,
                'source_cv_uuid' => $snapshot->uuid,
                'cv_hash' => $snapshot->contentHash,
                'confirmed_skills' => $parsed['confirmed'],
                'potential_skills' => $parsed['potential'],
                'proof_points' => $parsed['proofPoints'],
                'languages' => $parsed['languages'],
                'is_current' => true,
            ]);
        });
    }
}
