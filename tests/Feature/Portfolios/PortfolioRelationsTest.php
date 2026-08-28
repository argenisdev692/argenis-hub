<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioMediaEloquentModel;

/**
 * Both sides of the `portfolios.user_id` foreign key plus the
 * `portfolio_media.portfolio_id` child link — same reasoning as
 * `ServiceRelationsTest`: every relation the module relies on must exist, and
 * this is the test that keeps a future refactor honest.
 */
beforeEach(function (): void {
    Storage::fake('r2');
});

it('resolves the owner from the portfolio record', function (): void {
    $owner = User::factory()->create();
    $portfolio = PortfolioEloquentModel::factory()->create(['user_id' => $owner->id]);

    expect($portfolio->user)->toBeInstanceOf(User::class)
        ->and($portfolio->user?->getKey())->toBe($owner->getKey());
});

it('resolves the portfolios collection from the owner', function (): void {
    $owner = User::factory()->create();
    $portfolio = PortfolioEloquentModel::factory()->create(['user_id' => $owner->id]);

    expect($owner->portfolios)->toHaveCount(1)
        ->and($owner->portfolios->first()?->getKey())->toBe($portfolio->getKey());
});

it('resolves the ordered gallery from the portfolio', function (): void {
    $portfolio = PortfolioEloquentModel::factory()->create();
    PortfolioMediaEloquentModel::factory()->create(['portfolio_id' => $portfolio->id, 'sort_order' => 2]);
    PortfolioMediaEloquentModel::factory()->create(['portfolio_id' => $portfolio->id, 'sort_order' => 0]);

    expect($portfolio->media)->toHaveCount(2)
        ->and($portfolio->media->first()?->sort_order)->toBe(0);
});

it('eager-loads every direction without a lazy-loading violation', function (): void {
    $owner = User::factory()->create();
    $portfolio = PortfolioEloquentModel::factory()->create(['user_id' => $owner->id]);
    PortfolioMediaEloquentModel::factory()->create(['portfolio_id' => $portfolio->id]);

    $loaded = User::query()->with('portfolios:id,user_id,title')->findOrFail($owner->getKey());
    expect($loaded->portfolios->first()?->title)->toBeString();

    $child = PortfolioMediaEloquentModel::query()->with('portfolio:id,title')->firstOrFail();
    expect($child->portfolio->title)->toBeString();
});

it('leaves the owner alone when a portfolio is soft-deleted', function (): void {
    $owner = User::factory()->create();
    $portfolio = PortfolioEloquentModel::factory()->create(['user_id' => $owner->id]);

    $portfolio->delete();

    expect(User::query()->whereKey($owner->getKey())->exists())->toBeTrue()
        ->and(PortfolioEloquentModel::withTrashed()->whereKey($portfolio->getKey())->exists())->toBeTrue();
});
