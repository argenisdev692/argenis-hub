<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Domain\Ports\PasswordHistoryPort;
use Modules\Auth\Infrastructure\Persistence\Eloquent\Models\PasswordHistoryEloquentModel;
use Modules\Auth\Infrastructure\Persistence\Repositories\EloquentPasswordHistoryRepository;

/**
 * Spec 001 US-06 / FR-12 — the last five passwords may not be reused.
 */
test('registration seeds the first credential into the history', function (): void {
    $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'Str0ng-First-Passw0rd!',
        'password_confirmation' => 'Str0ng-First-Passw0rd!',
    ]);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->passwordHistories()->count())->toBe(1);
    expect(app(PasswordHistoryPort::class)->matchesRecent((string) $user->uuid, 'Str0ng-First-Passw0rd!'))->toBeTrue();
});

test('changing the password rejects one of the last five', function (): void {
    $user = User::factory()->create();
    $history = app(PasswordHistoryPort::class);

    $history->record((string) $user->uuid, Hash::make('Str0ng-Old-Passw0rd!'));

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'Str0ng-Old-Passw0rd!',
            'password_confirmation' => 'Str0ng-Old-Passw0rd!',
        ])
        ->assertSessionHasErrors('password');
});

test('changing the password accepts one that was never used', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'Str0ng-Fresh-Passw0rd!',
            'password_confirmation' => 'Str0ng-Fresh-Passw0rd!',
        ])
        ->assertSessionHasNoErrors();

    expect(app(PasswordHistoryPort::class)->matchesRecent((string) $user->uuid, 'Str0ng-Fresh-Passw0rd!'))->toBeTrue();
});

test('only the five most recent credentials are retained', function (): void {
    $user = User::factory()->create();
    $history = app(PasswordHistoryPort::class);

    foreach (range(1, 7) as $index) {
        $history->record((string) $user->uuid, Hash::make("Str0ng-Passw0rd-{$index}!"));
    }

    expect(PasswordHistoryEloquentModel::query()->count())->toBe(EloquentPasswordHistoryRepository::RETAINED_PASSWORDS);
    expect($history->matchesRecent((string) $user->uuid, 'Str0ng-Passw0rd-1!'))->toBeFalse();
    expect($history->matchesRecent((string) $user->uuid, 'Str0ng-Passw0rd-7!'))->toBeTrue();
});

test('history entries never expose the stored hash through serialization', function (): void {
    $user = User::factory()->create();

    app(PasswordHistoryPort::class)->record((string) $user->uuid, Hash::make('Str0ng-Passw0rd!'));

    $entry = PasswordHistoryEloquentModel::query()->firstOrFail()->toArray();

    expect($entry)->not->toHaveKey('password_hash');
    expect($entry)->not->toHaveKey('id');
});
