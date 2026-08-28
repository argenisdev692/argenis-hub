<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Portfolios\Infrastructure\Cache\PortfolioPublicFeedCache;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioMediaEloquentModel;

/**
 * `GET /api/public/portfolios` and its `/export` sibling — the payload the
 * landing page reads. No authentication, so the allowlist, the published-only
 * filter and the cache-invalidation behavior are the load-bearing assertions,
 * same reasoning as `PublicServiceApiTest`.
 */
beforeEach(function (): void {
    Storage::fake('r2');
    PortfolioPublicFeedCache::flush();
});

it('is reachable without authentication', function (): void {
    PortfolioEloquentModel::factory()->create();

    $this->getJson(route('api.public.portfolios.index'))->assertOk();
});

it('lists only public, published, non-deleted portfolios ordered by sort_order', function (): void {
    PortfolioEloquentModel::factory()->create(['title' => 'Second', 'sort_order' => 1]);
    PortfolioEloquentModel::factory()->create(['title' => 'First', 'sort_order' => 0]);
    PortfolioEloquentModel::factory()->hidden()->create(['title' => 'Private']);
    PortfolioEloquentModel::factory()->unpublished()->create(['title' => 'Draft']);
    PortfolioEloquentModel::factory()->scheduled()->create(['title' => 'Scheduled']);
    PortfolioEloquentModel::factory()->create(['title' => 'Deleted'])->delete();

    $this->getJson(route('api.public.portfolios.index'))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.title', 'First')
        ->assertJsonPath('1.title', 'Second');
});

it('exposes the resolved gallery urls and never the internal columns', function (): void {
    $portfolio = PortfolioEloquentModel::factory()->create();
    PortfolioMediaEloquentModel::factory()->count(2)->create(['portfolio_id' => $portfolio->id]);

    $response = $this->getJson(route('api.public.portfolios.index'))->assertOk();

    expect($response->json('0.gallery'))->toHaveCount(2);

    $body = $response->getContent() ?: '';
    expect($body)
        ->not->toContain('"user_id"')
        ->not->toContain('"deleted_at"')
        ->not->toContain('"is_public"')
        ->not->toContain('"cover_path"');
});

it('serves later callers from the cache', function (): void {
    PortfolioEloquentModel::factory()->create(['title' => 'Before']);

    $this->getJson(route('api.public.portfolios.index'))->assertJsonPath('0.title', 'Before');

    // Straight to the database, so no model event fires and no cache is flushed.
    PortfolioEloquentModel::query()->update(['title' => 'After']);

    $this->getJson(route('api.public.portfolios.index'))->assertJsonPath('0.title', 'Before');
});

it('flushes the cache the moment a portfolio is saved', function (): void {
    $portfolio = PortfolioEloquentModel::factory()->create(['title' => 'Before']);

    $this->getJson(route('api.public.portfolios.index'))->assertJsonPath('0.title', 'Before');

    $portfolio->update(['title' => 'After']);

    $this->getJson(route('api.public.portfolios.index'))->assertJsonPath('0.title', 'After');
});

it('throttles an unauthenticated caller on the list feed', function (): void {
    PortfolioEloquentModel::factory()->create();

    foreach (range(1, 60) as $ignored) {
        $this->getJson(route('api.public.portfolios.index'))->assertOk();
    }

    $this->getJson(route('api.public.portfolios.index'))->assertStatus(429);
});

describe('public export', function (): void {
    it('streams a published-only CSV without owner or status columns', function (): void {
        PortfolioEloquentModel::factory()->create(['title' => 'Public Project', 'client_name' => 'Acme']);
        PortfolioEloquentModel::factory()->hidden()->create(['title' => 'Hidden Project']);

        $content = $this->get(route('api.public.portfolios.export', ['format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        expect($content)
            ->toContain('Title', 'Client', 'Type', 'Tech Stack', 'Live URL', 'Published')
            ->toContain('Public Project')
            ->not->toContain('Hidden Project')
            ->not->toContain('Owner')
            ->not->toContain('Status');
    });

    it('renders a PDF', function (): void {
        PortfolioEloquentModel::factory()->create();

        $response = $this->get(route('api.public.portfolios.export', ['format' => 'pdf']))->assertOk();

        expect($response->headers->get('content-type'))->toContain('application/pdf');
    });

    it('rejects an unknown format', function (): void {
        $this->get(route('api.public.portfolios.export', ['format' => 'json']))->assertStatus(422);
    });

    it('throttles the export endpoint harder than the list feed', function (): void {
        PortfolioEloquentModel::factory()->create();

        foreach (range(1, 10) as $ignored) {
            $this->get(route('api.public.portfolios.export', ['format' => 'csv']))->assertOk();
        }

        $this->get(route('api.public.portfolios.export', ['format' => 'csv']))->assertStatus(429);
    });
});
