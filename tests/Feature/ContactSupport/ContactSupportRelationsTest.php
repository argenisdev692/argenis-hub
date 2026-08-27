<?php

declare(strict_types=1);

use App\Models\User;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

/**
 * Both sides of the nullable `contact_supports.user_id` foreign key — same
 * reasoning as `ServiceRelationsTest`: `ContactSupportEloquentModel::user()` and
 * `User::contactSupports()` must both exist, and this is the test that keeps a
 * future refactor honest.
 */
it('leaves the owner null for an anonymous submission', function (): void {
    $support = ContactSupportEloquentModel::factory()->create();

    expect($support->user_id)->toBeNull()
        ->and($support->user)->toBeNull();
});

it('resolves the owner from the request record', function (): void {
    $owner = User::factory()->create();
    $support = ContactSupportEloquentModel::factory()->forUser($owner)->create();

    expect($support->user)->toBeInstanceOf(User::class)
        ->and($support->user?->getKey())->toBe($owner->getKey());
});

it('resolves the contact-support collection from the owner', function (): void {
    $owner = User::factory()->create();
    $support = ContactSupportEloquentModel::factory()->forUser($owner)->create();

    expect($owner->contactSupports)->toHaveCount(1)
        ->and($owner->contactSupports->first()?->getKey())->toBe($support->getKey());
});

it('eager-loads both directions without a lazy-loading violation', function (): void {
    $owner = User::factory()->create();
    ContactSupportEloquentModel::factory()->forUser($owner)->create();

    $loaded = User::query()->with('contactSupports:id,user_id,subject')->findOrFail($owner->getKey());
    expect($loaded->contactSupports->first()?->subject)->toBeString();

    $child = ContactSupportEloquentModel::query()->with('user:id,first_name')->firstOrFail();
    expect($child->user?->first_name)->toBeString();
});

it('nulls the foreign key when the owner is deleted', function (): void {
    $owner = User::factory()->create();
    $support = ContactSupportEloquentModel::factory()->forUser($owner)->create();

    $owner->forceDelete();

    expect($support->refresh()->user_id)->toBeNull();
});
