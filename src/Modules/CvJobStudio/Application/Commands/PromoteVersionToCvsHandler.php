<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Illuminate\Support\Str;
use Modules\CvJobStudio\Domain\Exceptions\VersionNotFoundException;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvVersionEloquentModel;
use Modules\Cvs\Domain\Enums\CvNiche;
use Modules\Cvs\Domain\Enums\CvSource;
use Modules\Cvs\Domain\Ports\CvRepositoryPort;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;
use Shared\Domain\Ports\StoragePort;
use Throwable;

/**
 * Promotes a Studio version to the canonical CV store (the "set as primary"
 * confirmation): renders the version content to Markdown, stores it as a NEW
 * private R2 object + NEW `cvs` row, and flips `is_primary` onto it. The
 * previous primary is kept as history (SoftDeletes) — promotion supersedes,
 * never overwrites, so provenance, embeddings and audit trails stay intact.
 *
 * Cross-module write through the owning module's `CvRepositoryPort` — the
 * sanctioned write path; `CvSourcePort` stays read-only (GAP-A1). The new row
 * is re-parsed best-effort so RAG sees the promoted CV immediately; a parser
 * outage keeps the promotion and leaves re-parse to the UI retry.
 */
final readonly class PromoteVersionToCvsHandler
{
    public function __construct(
        private CvRepositoryPort $cvs,
        private StoragePort $storage,
        private ParseCvStructureHandler $parse,
    ) {}

    #[\NoDiscard]
    public function handle(string $versionUuid, int $userId, ?string $title = null): CvEloquentModel
    {
        $version = StudioCvVersionEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $versionUuid)
            ->first();

        if ($version === null) {
            throw new VersionNotFoundException("Version {$versionUuid} not found.");
        }

        $markdown = self::renderMarkdown((array) $version->content);
        $resolvedTitle = $title !== null && trim($title) !== ''
            ? trim($title) |> (fn (string $t): string => mb_substr($t, 0, 255))
            : "Studio {$version->purpose} ({$version->language})";
        $filePath = 'cvs/'.((string) Str::uuid7()).'.md';
        $this->storage->put($filePath, $markdown, 'private');

        try {
            $cv = $this->cvs->create([
                'title' => $resolvedTitle,
                'niche' => $this->nicheFor($version, $userId),
                'is_primary' => true,
                'file_path' => $filePath,
                'file_type' => 'md',
                'original_filename' => Str::slug($resolvedTitle).'.md',
                'raw_text' => $markdown,
                'source' => CvSource::Studio,
                'language' => $version->language,
                'parent_cv_uuid' => $this->parentUuid($version, $userId),
                'studio_version_uuid' => $version->uuid,
                'user_id' => $userId,
            ]);
        } catch (Throwable $exception) {
            $this->storage->delete($filePath);

            throw $exception;
        }

        try {
            (void) $this->parse->handle($cv->uuid, $userId);
        } catch (Throwable) {
            // Promotion stands; re-parse from the Studio UI when the parser recovers.
        }

        return $cv;
    }

    private function nicheFor(StudioCvVersionEloquentModel $version, int $userId): CvNiche
    {
        if ((int) $version->cv_id > 0) {
            $source = $this->cvs->findByIdForUser((int) $version->cv_id, $userId);

            if ($source instanceof CvEloquentModel) {
                return $source->niche;
            }
        }

        $primary = $this->cvs->findPrimaryForUser($userId);

        return $primary instanceof CvEloquentModel ? $primary->niche : CvNiche::Fullstack;
    }

    private function parentUuid(StudioCvVersionEloquentModel $version, int $userId): ?string
    {
        if ((int) $version->cv_id <= 0) {
            return null;
        }

        $source = $this->cvs->findByIdForUser((int) $version->cv_id, $userId);

        return $source instanceof CvEloquentModel ? $source->uuid : null;
    }

    /**
     * Version content is either ATS sections or a tailored summary/skills
     * pair — both render to plain Markdown (headings + bullets only, so the
     * promoted file stays parser-clean).
     */
    private static function renderMarkdown(array $content): string
    {
        if (isset($content['sections']) && is_array($content['sections'])) {
            $lines = [];

            foreach ($content['sections'] as $section) {
                $lines[] = '## '.trim((string) ($section['heading'] ?? 'Section'));

                foreach ((array) ($section['bullets'] ?? []) as $bullet) {
                    $text = is_array($bullet) ? (string) ($bullet['text'] ?? '') : (string) $bullet;

                    if (trim($text) !== '') {
                        $lines[] = '- '.trim($text);
                    }
                }

                $lines[] = '';
            }

            return trim(implode("\n", $lines));
        }

        $lines = [];

        if (isset($content['summary']) && trim((string) $content['summary']) !== '') {
            $lines[] = '## Summary';
            $lines[] = trim((string) $content['summary']);
            $lines[] = '';
        }

        if (isset($content['skills']) && is_array($content['skills'])) {
            $lines[] = '## Skills';

            foreach ($content['skills'] as $skill) {
                $text = is_array($skill) ? (string) ($skill['name'] ?? '') : (string) $skill;

                if (trim($text) !== '') {
                    $lines[] = '- '.trim($text);
                }
            }
        }

        return trim(implode("\n", $lines));
    }
}
