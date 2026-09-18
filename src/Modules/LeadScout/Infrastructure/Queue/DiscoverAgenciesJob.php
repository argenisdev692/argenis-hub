<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\LeadScout\Application\Commands\DiscoverAgenciesHandler;

/**
 * Weekly agency discovery (queue `lead-scout`). Idempotent per cache
 * window — re-runs skip already-executed combinations.
 *
 * @return array{queries: int, new_companies: int, skipped: int}
 */
#[Queue('lead-scout')]
#[Tries(2)]
#[Timeout(600)]
#[Backoff([60, 300])]
final class DiscoverAgenciesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly ?string $wave = null,
        public readonly ?string $country = null,
        public readonly ?string $family = null,
    ) {}

    public function handle(DiscoverAgenciesHandler $discover): array
    {
        return $discover->handle($this->wave, $this->country, $this->family);
    }
}
