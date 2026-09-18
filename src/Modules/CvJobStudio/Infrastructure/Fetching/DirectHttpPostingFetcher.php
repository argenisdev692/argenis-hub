<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Fetching;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\PostingTextFetcherPort;
use Modules\CvJobStudio\Domain\Services\NeverFetchHostPolicy;
use Modules\CvJobStudio\Domain\Services\OutboundUrlGuard;
use Modules\CvJobStudio\Domain\Services\RobotsTxtPolicy;

/** Plain HTTP fetch with guard + robots pre-checks (FR-14, FR-34). */
final readonly class DirectHttpPostingFetcher implements PostingTextFetcherPort
{
    public function __construct(
        private OutboundUrlGuard $guard,
        private RobotsTxtPolicy $robots,
        private NeverFetchHostPolicy $access,
    ) {}

    public function stepName(): string
    {
        return 'direct_http';
    }

    public function fetch(string $url): ?array
    {
        if (! $this->guard->allowed($url) || ! $this->robots->mayFetch($url)) {
            return null;
        }

        try {
            $response = Http::timeout(5)->retry(1, 500)->get($url);
        } catch (\Throwable) {
            return null;
        }

        if ($response->failed() || trim($text = strip_tags($response->body())) === '') {
            return null;
        }

        return ['text' => $text, 'completeness' => 'full', 'cost_micros' => 0];
    }
}
