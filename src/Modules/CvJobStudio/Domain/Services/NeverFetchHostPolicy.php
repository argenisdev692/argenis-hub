<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Access-mode enforcement (T-107, FR-50, FR-52). Sources whose terms forbid
 * automated access (`link_only`) or that are resolve-only signals
 * (`resolve_only`) never reach a fetcher, probe or ladder step — including
 * when theirs is the only copy of a posting, and including tier-3 resolution
 * searches (they pass these hosts in `exclude_domains`).
 */
final readonly class NeverFetchHostPolicy
{
    public const string LINK_ONLY = 'link_only';

    public const string RESOLVE_ONLY = 'resolve_only';

    #[\NoDiscard]
    public function mayFetch(string $accessMode): bool
    {
        return ! in_array($accessMode, [self::LINK_ONLY, self::RESOLVE_ONLY], true);
    }

    #[\NoDiscard]
    public function mayProbe(string $accessMode): bool
    {
        return $this->mayFetch($accessMode);
    }
}
