<?php

declare(strict_types=1);

use App\Models\CompanyData;
use App\Models\User;

/**
 * Both sides of the `company_data.user_id` foreign key.
 *
 * The project rule is that no FK ships one-directional, and this is the test
 * that keeps it honest: `CompanyData::user()` was already there, `User::companyData()`
 * arrived with this module, and a future refactor that drops either one fails here.
 *
 * `hasOne`, not `hasMany`, because the record is a singleton — the module exposes
 * no route that could create a second row for the same owner.
 */
it('resolves the owner from the company record', function (): void {
    $owner = User::factory()->create();
    $company = CompanyData::factory()->create(['user_id' => $owner->id]);

    expect($company->user)->toBeInstanceOf(User::class)
        ->and($company->user?->getKey())->toBe($owner->getKey());
});

it('resolves the company record from its owner', function (): void {
    $owner = User::factory()->create();
    $company = CompanyData::factory()->create(['user_id' => $owner->id]);

    expect($owner->companyData)->toBeInstanceOf(CompanyData::class)
        ->and($owner->companyData?->getKey())->toBe($company->getKey());
});

it('eager-loads both directions without a lazy-loading violation', function (): void {
    $owner = User::factory()->create();
    CompanyData::factory()->create(['user_id' => $owner->id]);

    // Model::shouldBeStrict() is on outside production, so an un-eager-loaded
    // access here would throw rather than quietly firing an extra query. The
    // column lists must name real columns — `users` stores `first_name`, not the
    // composed `name` an eager-load string would happily accept and then return
    // empty.
    $loaded = User::query()->with('companyData:id,user_id,company_name')->findOrFail($owner->getKey());

    expect($loaded->companyData?->company_name)->toBeString();

    $child = CompanyData::query()->with('user:id,first_name')->firstOrFail();

    expect($child->user?->first_name)->toBeString();
});

it('leaves the owner alone when the company record is soft-deleted', function (): void {
    $owner = User::factory()->create();
    $company = CompanyData::factory()->create(['user_id' => $owner->id]);

    $company->delete();

    expect($owner->fresh()?->companyData)->toBeNull()
        ->and(User::query()->whereKey($owner->getKey())->exists())->toBeTrue()
        ->and(CompanyData::withTrashed()->whereKey($company->getKey())->exists())->toBeTrue();
});
