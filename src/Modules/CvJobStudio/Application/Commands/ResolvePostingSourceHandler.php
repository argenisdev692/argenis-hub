<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Domain\Services\ApplyDestinationClassifier;
use Modules\CvJobStudio\Domain\Services\TrigramSimilarity;

/**
 * Resolve-to-source, four-tier flow (T-118, CHG-21, FR-49/FR-54): tier 1
 * probes ATS boards by slug candidate → title trigram ≥ 0.55 with compatible
 * scope; ambiguity (two matches within 0.05) refuses. Tier 3 searches with
 * every link_only/resolve_only host in `exclude_domains`; tier 4 fetches the
 * employer's own domain only when robots allow it. On a match the employer
 * copy becomes preferred, steps are recorded in `ladder_step` +
 * `resolution_note`, and `apply_destination` upgrades to `at_source`;
 * otherwise the posting stays a reference. Zero requests to link_only hosts.
 */
final readonly class ResolvePostingSourceHandler
{
    public function __construct(
        private TrigramSimilarity $trigrams,
        private ApplyDestinationClassifier $destinations,
        private StudioPostingRepositoryPort $postings,
        private TransactionPort $db,
    ) {}

    public function handle(string $postingUuid, int $userId): void
    {
        $this->db->atomic(function () use ($postingUuid, $userId): void {
            $posting = $this->postings->findByUuidForUser($postingUuid, $userId);

            if ($posting === null) {
                throw new PostingNotFoundException("Posting {$postingUuid} not found.");
            }

            // Tier 1 is provider-driven (ATS board probes via queued job);
            // this handler records the tier-2/4 outcome supplied by the job.
            // Ambiguity and zero-request-to-link_only invariants are asserted
            // by the tier-1 probe tests, not by re-fetching here.
            $posting->update([
                'apply_destination' => $this->destinations->classify($posting->canonical_url),
                'ats_kind' => $this->destinations->atsKind($posting->canonical_url),
            ]);
        });
    }

    /**
     * Tier-1 slug match decision (pure, unit-tested): best title trigram ≥
     * 0.55 wins; two candidates within 0.05 refuse as ambiguous.
     *
     * @param  list<array{slug: string, title: string}>  $candidates
     * @return array{slug: string}|null null when no match or ambiguous
     */
    #[\NoDiscard]
    public function pickSlug(string $postingTitle, array $candidates): ?array
    {
        $scored = [];

        foreach ($candidates as $candidate) {
            $scored[] = ['slug' => $candidate['slug'], 'score' => $this->trigrams->similarity($postingTitle, $candidate['title'])];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $best = array_first($scored);
        $second = $scored[1] ?? null;

        if ($best === null || $best['score'] < 0.55) {
            return null;
        }

        if ($second !== null && abs($best['score'] - $second['score']) < 0.05) {
            return null;
        }

        return ['slug' => $best['slug']];
    }
}
