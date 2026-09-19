<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Config\Repository as ConfigRepository;
use Modules\CvJobStudio\Application\Commands\ResolvePostingSourceHandler;
use Modules\CvJobStudio\Domain\Exceptions\BudgetExceededException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Domain\Services\ApplyDestinationClassifier;
use Modules\CvJobStudio\Domain\Services\PostingTextMinimiser;
use Modules\CvJobStudio\Domain\Services\RobotsTxtPolicy;
use Modules\CvJobStudio\Domain\Services\TrigramSimilarity;
use Modules\CvJobStudio\Infrastructure\Ai\NarrateMatchAgent;
use Modules\CvJobStudio\Infrastructure\Budgets\StudioBudgetLedger;
use Modules\CvJobStudio\Infrastructure\Fetching\FirecrawlPostingFetcher;
use Modules\CvJobStudio\Infrastructure\Fetching\OutboundUrlGuard;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioBudgetEloquentModel;
use Shared\Infrastructure\AI\ProviderFailover;
use Shared\Infrastructure\Research\FirecrawlClientInterface;

it('orders failover attempts with the preferred provider first (T-152)', function (): void {
    $failover = new ProviderFailover(new ConfigRepository(['ai' => ['failover_order' => 'openai,anthropic']]));

    expect($failover->attempts('anthropic'))->toBe(['anthropic', 'openai'])
        ->and($failover->attempts())->toBe(['openai', 'anthropic']);
});

it('refuses a call that would breach the period budget before spending (SC-8, T-100)', function (): void {
    $user = User::factory()->create();

    StudioBudgetEloquentModel::query()->create([
        'user_id' => $user->id,
        'period' => now()->format('Y-m'),
        'category' => 'llm',
        'limit_micros' => 1000,
        'spent_micros' => 1000,
    ]);

    (new StudioBudgetLedger)->ensure('llm', $user->id);
})->throws(BudgetExceededException::class);

it('always requests the basic proxy and never auto or stealth (T-159, SC-20)', function (): void {
    $fake = new class implements FirecrawlClientInterface
    {
        public ?string $proxy = null;

        public function scrape(string $url, ?string $proxy = null): ?string
        {
            $this->proxy = $proxy;

            return 'text';
        }
    };

    $fetcher = new FirecrawlPostingFetcher(
        $fake,
        new OutboundUrlGuard(resolver: static fn (string $host): array => ['93.184.215.14']),
        new RobotsTxtPolicy,
    );

    $result = $fetcher->fetch('https://boards.greenhouse.io/acme/jobs/1');

    expect($result['ladder_step'] ?? 'firecrawl')->toBe('firecrawl')
        ->and($fake->proxy)->toBe('basic')
        ->and($fetcher->stepName())->toBe('firecrawl');
});

it('picks the employer slug at trigram 0.55 and refuses ambiguity (T-118)', function (): void {
    $handler = new ResolvePostingSourceHandler(
        new TrigramSimilarity,
        new ApplyDestinationClassifier,
        app(StudioPostingRepositoryPort::class),
        app(TransactionPort::class),
    );

    expect($handler->pickSlug('Senior Laravel Developer', [
        ['slug' => 'acme', 'title' => 'Senior Laravel Developer'],
        ['slug' => 'other', 'title' => 'Junior Accountant'],
    ]))->toBe(['slug' => 'acme']);

    // Two identical titles under different slugs refuse as ambiguous.
    expect($handler->pickSlug('Senior Laravel Developer', [
        ['slug' => 'acme', 'title' => 'Senior Laravel Developer'],
        ['slug' => 'acme-inc', 'title' => 'Senior Laravel Developer'],
    ]))->toBeNull();

    // Below-threshold best match refuses.
    expect($handler->pickSlug('Senior Laravel Developer', [
        ['slug' => 'other', 'title' => 'Junior Accountant'],
    ]))->toBeNull();
});

it('keeps numbers out of the match narrative schema (T-156, NFR-2)', function (): void {
    $source = (string) file_get_contents((string) (new ReflectionClass(NarrateMatchAgent::class))->getFileName());

    expect($source)->toContain("'why'")
        ->and($source)->toContain("'gaps'")
        ->and($source)->not->toContain('integer()')
        ->and($source)->not->toContain('number()')
        ->and($source)->not->toContain('float()');
});

it('minimises posting text deterministically (T-149)', function (): void {
    $minimiser = new PostingTextMinimiser;

    expect($minimiser->minimise('We need Laravel.', null))->toBe('We need Laravel.');
});
