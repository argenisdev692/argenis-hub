<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PortfolioSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

/**
 * The showcase projects the public landing-page feed renders. The seeder is
 * keyed by the canonical `uuid`, so the contract under test is: exact payload on
 * a fresh database, and idempotency (plus soft-delete recovery) on a re-run.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
});

it('seeds the three showcase portfolios owned by the super admin', function (): void {
    $this->seed(PortfolioSeeder::class);

    expect(PortfolioEloquentModel::query()->count())->toBe(3);

    $servispin = PortfolioEloquentModel::query()
        ->where('uuid', '019fd7b6-3cf6-7050-83a4-2b7cdd56d5a2')
        ->firstOrFail();

    expect($servispin->title)->toBe('SERVISPIN — Appointment Management and Technical Support System')
        ->and($servispin->client_name)->toBe('SERVISPIN')
        ->and($servispin->project_type)->toBe('Business Website')
        ->and($servispin->tech_stack)->toBe(['PHP', 'Laravel', 'Livewire', 'Alpine JS', 'Tailwind CSS'])
        ->and($servispin->live_url)->toBe('https://servispin.net/')
        ->and($servispin->published_at?->toDateString())->toBe('2026-08-06')
        ->and($servispin->is_public)->toBeTrue()
        ->and($servispin->cover_path)->toBe('portfolios/cover/003a5dd7-eb71-40b0-b81b-993a61c77a4c/ser-1.png')
        ->and($servispin->video_path)->toBe('portfolios/video/0b5b66ca-3e5f-4f47-8ef9-afcf033bb685/video-servispin.mp4')
        ->and($servispin->sort_order)->toBe(0)
        ->and($servispin->user->hasRole('SUPER_ADMIN'))->toBeTrue();

    expect(PortfolioEloquentModel::query()->orderBy('sort_order')->pluck('client_name')->all())
        ->toBe(['SERVISPIN', 'AQUASHIELD RESTORATION LLC', 'VIDULA']);
});

it('exposes every seeded portfolio through the published feed scope', function (): void {
    $this->seed(PortfolioSeeder::class);

    expect(PortfolioEloquentModel::published()->count())->toBe(3);
});

it('updates instead of duplicating when run twice', function (): void {
    $this->seed(PortfolioSeeder::class);

    PortfolioEloquentModel::query()
        ->where('uuid', '019fd7bc-502e-727c-93db-30401a624137')
        ->update(['title' => 'STALE TITLE']);

    $this->seed(PortfolioSeeder::class);

    expect(PortfolioEloquentModel::query()->count())->toBe(3)
        ->and(PortfolioEloquentModel::query()->where('uuid', '019fd7bc-502e-727c-93db-30401a624137')->value('title'))
        ->toBe('VIDULA');
});

it('restores a soft-deleted portfolio on a re-run', function (): void {
    $this->seed(PortfolioSeeder::class);

    PortfolioEloquentModel::query()
        ->where('uuid', '019fd7b9-dfdf-734d-baf9-784b206e1067')
        ->firstOrFail()
        ->delete();

    $this->seed(PortfolioSeeder::class);

    expect(PortfolioEloquentModel::query()->count())->toBe(3)
        ->and(PortfolioEloquentModel::onlyTrashed()->count())->toBe(0);
});

it('fails loudly when no super admin exists', function (): void {
    PortfolioEloquentModel::query()->forceDelete();
    User::query()->role('SUPER_ADMIN')->get()->each(fn (User $user): ?bool => $user->forceDelete());

    expect(fn () => $this->seed(PortfolioSeeder::class))
        ->toThrow(RuntimeException::class);
});
