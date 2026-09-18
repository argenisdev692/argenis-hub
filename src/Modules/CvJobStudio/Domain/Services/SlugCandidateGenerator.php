<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * ATS board slug candidates from a company name (T-009, T-118): each pattern
 * is probed (a 404 means "not a customer") and the hit is recorded.
 * Budget-guarded, rate-limited, negative results cached 30 days by the job.
 */
final readonly class SlugCandidateGenerator
{
    /** @return list<string> */
    #[\NoDiscard]
    public function candidates(string $companyName): array
    {
        $base = strtolower((string) preg_replace('/[^a-z0-9]+/', '', PostingFingerprint::normalize($companyName)));

        if ($base === '') {
            return [];
        }

        $candidates = [$base];

        foreach (['inc', 'ltd', 'gmbh', 'sas', 'sl', 'lda', 'bv', 'ab', 'oy', 'aps', 'srl', 'corp', 'co', 'technologies', 'labs'] as $suffix) {
            if (str_ends_with($base, $suffix) && strlen($base) > strlen($suffix)) {
                $candidates[] = substr($base, 0, -strlen($suffix));
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }
}
