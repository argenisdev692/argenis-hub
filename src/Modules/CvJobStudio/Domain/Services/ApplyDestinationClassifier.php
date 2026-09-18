<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

use Uri\Rfc3986\Uri;

/**
 * How a posting is reached (T-108, FR-43/FR-46): employer's own site or
 * hiring endpoint, job board, aggregator repost, staffing agency, or social
 * network listing. Pure URL-pattern table.
 */
final readonly class ApplyDestinationClassifier
{
    public const string AT_SOURCE = 'at_source';

    public const string BOARD = 'board';

    public const string AGGREGATOR = 'aggregator';

    public const string AGENCY = 'agency';

    public const string SOCIAL = 'social';

    public const string UNKNOWN = 'unknown';

    /** @var array<string, string> */
    private const array ATS_HOSTS = [
        'boards.greenhouse.io' => 'greenhouse',
        'jobs.lever.co' => 'lever',
        'jobs.ashbyhq.com' => 'ashby',
        'myworkdayjobs.com' => 'workday',
        'icims.com' => 'icims',
        'recruitee.com' => 'recruitee',
        'apply.workable.com' => 'workable',
        'teamtailor.com' => 'teamtailor',
        'smartrecruiters.com' => 'smartrecruiters',
        'personio.' => 'personio',
    ];

    #[\NoDiscard]
    public function classify(string $url): string
    {
        $host = self::hostOf($url);

        foreach (self::ATS_HOSTS as $pattern => $kind) {
            if (str_contains($host, $pattern)) {
                return self::AT_SOURCE;
            }
        }

        if (str_contains($host, 'linkedin.com') || str_contains($host, 'facebook.com') || str_contains($host, 'x.com')) {
            return self::SOCIAL;
        }

        return self::BOARD;
    }

    #[\NoDiscard]
    public function atsKind(string $url): ?string
    {
        $host = self::hostOf($url);

        foreach (self::ATS_HOSTS as $pattern => $kind) {
            if (str_contains($host, $pattern)) {
                return $kind;
            }
        }

        return null;
    }

    #[\NoDiscard]
    private static function hostOf(string $url): string
    {
        try {
            return mb_strtolower(new Uri(trim($url))->getHost() ?? '');
        } catch (\Throwable) {
            return '';
        }
    }
}
