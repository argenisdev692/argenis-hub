<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\CvSourcePort;
use Modules\CvJobStudio\Domain\Ports\CvStructureParserPort;
use Modules\CvJobStudio\Domain\Ports\ProjectSourcePort;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvBulletEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvEntryEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvSkillEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvStructureEloquentModel;

/**
 * Parses a CV into addressable rows (T-068, FR-4): reads `Modules\Cvs`
 * read-only (never modifies it — GAP-A1), merges portfolio projects as
 * `kind = project` entries (T-008), and stores the parser rows. Re-runnable;
 * `raw_text` stays the source of truth.
 */
final readonly class ParseCvStructureHandler
{
    public function __construct(
        private CvSourcePort $cvs,
        private ProjectSourcePort $projects,
        private CvStructureParserPort $parser,
        private TransactionPort $db,
    ) {}

    #[\NoDiscard]
    public function handle(?string $cvUuid, int $userId): StudioCvStructureEloquentModel
    {
        return $this->db->atomic(function () use ($cvUuid, $userId): StudioCvStructureEloquentModel {
            $cv = $cvUuid !== null
                ? $this->cvs->findForUser($cvUuid, $userId)
                : $this->cvs->primaryForUser($userId);

            if ($cv === null || ($cv['raw_text'] ?? null) === null) {
                throw new PostingNotFoundException('No CV with extracted text found.');
            }

            $parsed = $this->parser->parse((string) $cv['raw_text']);

            $structure = StudioCvStructureEloquentModel::query()->create([
                'user_id' => $userId,
                'cv_id' => $cv['cv_id'],
                'source_text_hash' => hash('sha256', (string) $cv['raw_text']),
                'parser_version' => $parsed['parser_version'],
                'parsed_at' => now(),
                'profile_facts' => $parsed['profile_facts'],
            ]);

            $entryIds = [];

            foreach ($parsed['entries'] as $ordinal => $entry) {
                $created = StudioCvEntryEloquentModel::query()->create([
                    'user_id' => $userId,
                    'structure_id' => $structure->id,
                    'kind' => $entry['kind'] ?? 'experience',
                    'ordinal' => $ordinal,
                    'organization' => $entry['organization'] ?? null,
                    'role_title' => $entry['role_title'] ?? null,
                    'location' => $entry['location'] ?? null,
                    'is_current' => (bool) ($entry['is_current'] ?? false),
                ]);

                $entryIds[] = $created->id;
            }

            foreach ($parsed['bullets'] as $ordinal => $bullet) {
                StudioCvBulletEloquentModel::query()->create([
                    'user_id' => $userId,
                    'structure_id' => $structure->id,
                    'entry_id' => $entryIds[$bullet['entry_ordinal'] ?? 0] ?? $entryIds[0] ?? 0,
                    'ordinal' => $ordinal,
                    'text' => $bullet['text'] ?? '',
                    'has_metric' => (bool) ($bullet['has_metric'] ?? false),
                    'xyz_complete' => (bool) ($bullet['xyz_complete'] ?? false),
                    'char_count' => mb_strlen((string) ($bullet['text'] ?? '')),
                ]);
            }

            foreach ($parsed['skills'] as $skill) {
                StudioCvSkillEloquentModel::query()->create([
                    'user_id' => $userId,
                    'structure_id' => $structure->id,
                    'canonical_name' => $skill['canonical_name'] ?? 'unknown',
                    'raw_name' => $skill['raw_name'] ?? null,
                    'nature' => $skill['nature'] ?? 'hard',
                    'evidence' => $skill['evidence'] ?? 'list_only',
                ]);
            }

            foreach ($this->projects->projectsForUser($userId) as $ordinal => $project) {
                StudioCvEntryEloquentModel::query()->create([
                    'user_id' => $userId,
                    'structure_id' => $structure->id,
                    'kind' => 'project',
                    'ordinal' => count($entryIds) + $ordinal,
                    'organization' => $project['title'],
                    'role_title' => $project['url'],
                ]);
            }

            return $structure;
        });
    }
}
