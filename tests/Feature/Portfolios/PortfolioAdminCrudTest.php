<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioMediaEloquentModel;

/**
 * `/data/admin/portfolios` — the JSON CRUD surface the admin table consumes.
 * Every write is guarded by its own `*_PORTFOLIOS` permission (seeded ahead of
 * this module in `RolePermissionSeeder::MODULES`).
 */
beforeEach(function (): void {
    Storage::fake('r2');
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @param  list<string>  $permissions
 */
function portfolioOperator(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function portfolioPayload(array $overrides = []): array
{
    return [
        'title' => 'Acme Rebrand',
        'client_name' => 'Acme Inc.',
        'project_type' => 'Web App',
        'tech_stack' => ['Laravel', 'Vue', 'PostgreSQL'],
        'live_url' => 'https://acme.example',
        'published_at' => '2026-01-15T10:00:00+00:00',
        'is_public' => true,
        'cover_path' => 'portfolios/acme/cover.jpg',
        'video_path' => null,
        'description' => 'Full brand and product site rebuild.',
        'sort_order' => 3,
        ...$overrides,
    ];
}

describe('authorization', function (): void {
    it('turns a guest away from every route', function (): void {
        $portfolio = PortfolioEloquentModel::factory()->create();

        $this->getJson(route('portfolios.admin.index'))->assertUnauthorized();
        $this->getJson(route('portfolios.admin.show', $portfolio->uuid))->assertUnauthorized();
        $this->postJson(route('portfolios.admin.store'), portfolioPayload())->assertUnauthorized();
        $this->putJson(route('portfolios.admin.update', $portfolio->uuid), portfolioPayload())->assertUnauthorized();
        $this->deleteJson(route('portfolios.admin.destroy', $portfolio->uuid))->assertUnauthorized();
        $this->patchJson(route('portfolios.admin.restore', $portfolio->uuid))->assertUnauthorized();
        $this->postJson(route('portfolios.admin.bulk-delete'), ['uuids' => [$portfolio->uuid]])->assertUnauthorized();
        $this->postJson(route('portfolios.admin.bulk-restore'), ['uuids' => [$portfolio->uuid]])->assertUnauthorized();
    });

    it('refuses a signed-in user who holds no portfolios permission', function (): void {
        $this->actingAs(portfolioOperator([]))
            ->getJson(route('portfolios.admin.index'))
            ->assertForbidden();
    });

    it('refuses a reader on the write routes', function (): void {
        $portfolio = PortfolioEloquentModel::factory()->create();

        $this->actingAs(portfolioOperator(['VIEW_ANY_PORTFOLIOS', 'VIEW_PORTFOLIOS']))
            ->putJson(route('portfolios.admin.update', $portfolio->uuid), portfolioPayload())
            ->assertForbidden();
    });
});

describe('listing', function (): void {
    it('paginates active and soft-deleted portfolios together by default', function (): void {
        PortfolioEloquentModel::factory()->count(2)->create();
        PortfolioEloquentModel::factory()->create()->delete();

        $this->actingAs(portfolioOperator(['VIEW_ANY_PORTFOLIOS']))
            ->getJson(route('portfolios.admin.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('serializes the paginator flat, not nested under a "meta" key', function (): void {
        PortfolioEloquentModel::factory()->create();

        $this->actingAs(portfolioOperator(['VIEW_ANY_PORTFOLIOS']))
            ->getJson(route('portfolios.admin.index'))
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'per_page', 'from', 'to', 'total'])
            ->assertJsonMissingPath('meta');
    });

    it('filters by search term', function (): void {
        PortfolioEloquentModel::factory()->create([
            'title' => 'Business Website', 'client_name' => 'Acme', 'project_type' => 'Web App',
        ]);
        PortfolioEloquentModel::factory()->create([
            'title' => 'Retail Store', 'client_name' => 'Commerce Partners', 'project_type' => 'Web App',
        ]);

        $this->actingAs(portfolioOperator(['VIEW_ANY_PORTFOLIOS']))
            ->getJson(route('portfolios.admin.index', ['search' => 'commerce']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Retail Store');
    });

    it('never exposes the auto-increment key or the owner column', function (): void {
        PortfolioEloquentModel::factory()->create();

        $response = $this->actingAs(portfolioOperator(['VIEW_ANY_PORTFOLIOS']))
            ->getJson(route('portfolios.admin.index'))
            ->assertOk();

        expect($response->json('data.0'))
            ->not->toHaveKey('id')
            ->not->toHaveKey('user_id');
    });
});

describe('creating', function (): void {
    it('creates a portfolio owned by the acting operator', function (): void {
        $operator = portfolioOperator(['CREATE_PORTFOLIOS']);

        $this->actingAs($operator)
            ->postJson(route('portfolios.admin.store'), portfolioPayload())
            ->assertCreated()
            ->assertJsonPath('title', 'Acme Rebrand')
            ->assertJsonPath('tech_stack', ['Laravel', 'Vue', 'PostgreSQL']);

        $portfolio = PortfolioEloquentModel::query()->where('title', 'Acme Rebrand')->firstOrFail();
        expect($portfolio->user_id)->toBe($operator->id);
    });

    it('stores and returns the gallery when media keys are supplied', function (): void {
        $this->actingAs(portfolioOperator(['CREATE_PORTFOLIOS']))
            ->postJson(route('portfolios.admin.store'), portfolioPayload([
                'media' => ['portfolios/acme/1.jpg', 'portfolios/acme/2.jpg'],
            ]))
            ->assertCreated()
            ->assertJsonCount(2, 'gallery')
            ->assertJsonPath('media_paths', ['portfolios/acme/1.jpg', 'portfolios/acme/2.jpg']);

        $portfolio = PortfolioEloquentModel::query()->where('title', 'Acme Rebrand')->firstOrFail();
        expect($portfolio->media)->toHaveCount(2)
            ->and($portfolio->media->first()?->sort_order)->toBe(0);
    });

    it('rejects invalid input', function (array $overrides, string $field): void {
        $this->actingAs(portfolioOperator(['CREATE_PORTFOLIOS']))
            ->postJson(route('portfolios.admin.store'), portfolioPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'missing title' => [['title' => ''], 'title'],
        'missing client' => [['client_name' => ''], 'client_name'],
        'missing project type' => [['project_type' => ''], 'project_type'],
        'project type too long' => [['project_type' => str_repeat('a', 51)], 'project_type'],
        'malformed live url' => [['live_url' => 'not-a-url'], 'live_url'],
        'malformed published_at' => [['published_at' => 'yesterday-ish'], 'published_at'],
        'description too long' => [['description' => str_repeat('a', 5001)], 'description'],
        'non-string tech badge' => [['tech_stack' => [123]], 'tech_stack.0'],
    ]);
});

describe('updating', function (): void {
    it('persists the editable fields', function (): void {
        $portfolio = PortfolioEloquentModel::factory()->create(['title' => 'Before']);

        $this->actingAs(portfolioOperator(['UPDATE_PORTFOLIOS']))
            ->putJson(route('portfolios.admin.update', $portfolio->uuid), portfolioPayload(['title' => 'After']))
            ->assertOk()
            ->assertJsonPath('title', 'After');

        expect($portfolio->refresh()->title)->toBe('After');
    });

    it('replaces the gallery wholesale when new media keys are sent', function (): void {
        $portfolio = PortfolioEloquentModel::factory()->create();
        PortfolioMediaEloquentModel::factory()->count(3)->create(['portfolio_id' => $portfolio->id]);

        $this->actingAs(portfolioOperator(['UPDATE_PORTFOLIOS']))
            ->putJson(route('portfolios.admin.update', $portfolio->uuid), portfolioPayload([
                'media' => ['portfolios/new/only.jpg'],
            ]))
            ->assertOk()
            ->assertJsonPath('media_paths', ['portfolios/new/only.jpg']);

        expect($portfolio->refresh()->media)->toHaveCount(1);
    });

    it('leaves the gallery untouched when no media key is sent', function (): void {
        $portfolio = PortfolioEloquentModel::factory()->create();
        PortfolioMediaEloquentModel::factory()->count(2)->create(['portfolio_id' => $portfolio->id]);

        $this->actingAs(portfolioOperator(['UPDATE_PORTFOLIOS']))
            ->putJson(route('portfolios.admin.update', $portfolio->uuid), portfolioPayload())
            ->assertOk()
            ->assertJsonCount(2, 'gallery');
    });

    it('records the change in the audit trail', function (): void {
        $portfolio = PortfolioEloquentModel::factory()->create(['title' => 'Before']);

        $this->actingAs(portfolioOperator(['UPDATE_PORTFOLIOS']))
            ->putJson(route('portfolios.admin.update', $portfolio->uuid), portfolioPayload(['title' => 'After']))
            ->assertOk();

        $activity = $portfolio->activitiesAsSubject()->where('event', 'updated')->latest('id')->first();
        $changes = $activity?->attribute_changes?->toArray() ?? [];

        expect($activity?->log_name)->toBe('portfolios.portfolio')
            ->and($changes['attributes']['title'] ?? null)->toBe('After');
    });
});

describe('deleting and restoring', function (): void {
    it('soft-deletes a portfolio', function (): void {
        $portfolio = PortfolioEloquentModel::factory()->create();

        $this->actingAs(portfolioOperator(['DELETE_PORTFOLIOS']))
            ->deleteJson(route('portfolios.admin.destroy', $portfolio->uuid))
            ->assertNoContent();

        expect(PortfolioEloquentModel::query()->whereKey($portfolio->getKey())->exists())->toBeFalse()
            ->and(PortfolioEloquentModel::withTrashed()->whereKey($portfolio->getKey())->exists())->toBeTrue();
    });

    it('restores a soft-deleted portfolio', function (): void {
        $portfolio = PortfolioEloquentModel::factory()->create();
        $portfolio->delete();

        $this->actingAs(portfolioOperator(['RESTORE_PORTFOLIOS']))
            ->patchJson(route('portfolios.admin.restore', $portfolio->uuid))
            ->assertOk()
            ->assertJsonPath('deleted_at', null);

        expect($portfolio->fresh())->not->toBeNull();
    });
});

describe('bulk operations', function (): void {
    it('bulk soft-deletes the selected portfolios', function (): void {
        $portfolios = PortfolioEloquentModel::factory()->count(3)->create();

        $this->actingAs(portfolioOperator(['BULK_DELETE_PORTFOLIOS']))
            ->postJson(route('portfolios.admin.bulk-delete'), ['uuids' => $portfolios->pluck('uuid')->all()])
            ->assertOk()
            ->assertJsonPath('deleted', 3);

        expect(PortfolioEloquentModel::query()->count())->toBe(0)
            ->and(PortfolioEloquentModel::withTrashed()->count())->toBe(3);
    });

    it('bulk restores the selected portfolios', function (): void {
        $portfolios = PortfolioEloquentModel::factory()->count(2)->create();
        PortfolioEloquentModel::query()->whereIn('uuid', $portfolios->pluck('uuid'))->delete();

        $this->actingAs(portfolioOperator(['BULK_RESTORE_PORTFOLIOS']))
            ->postJson(route('portfolios.admin.bulk-restore'), ['uuids' => $portfolios->pluck('uuid')->all()])
            ->assertOk()
            ->assertJsonPath('restored', 2);

        expect(PortfolioEloquentModel::query()->count())->toBe(2);
    });

    it('refuses bulk delete without the bulk permission', function (): void {
        $portfolio = PortfolioEloquentModel::factory()->create();

        $this->actingAs(portfolioOperator(['DELETE_PORTFOLIOS']))
            ->postJson(route('portfolios.admin.bulk-delete'), ['uuids' => [$portfolio->uuid]])
            ->assertForbidden();
    });

    it('rejects a malformed uuid in the list', function (): void {
        $portfolio = PortfolioEloquentModel::factory()->create();

        $this->actingAs(portfolioOperator(['BULK_DELETE_PORTFOLIOS']))
            ->postJson(route('portfolios.admin.bulk-delete'), ['uuids' => [$portfolio->uuid, 'not-a-uuid']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('uuids.1');
    });
});
