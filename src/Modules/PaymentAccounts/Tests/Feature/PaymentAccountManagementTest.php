<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function accountAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

/**
 * @return array<string, mixed>
 */
function validAccountPayload(array $overrides = []): array
{
    return [
        'method' => PaymentMethod::BankTransfer->value,
        'label' => 'Montepio EUR',
        'currency' => 'EUR',
        'beneficiary' => 'Argenis Jose Carrillo Gonzalez',
        'bank_name' => 'Montepio',
        'iban' => 'PT50003600119910006305349',
        'bic' => 'MPIOPTPL',
        'is_default' => true,
        'is_active' => true,
        'sort_order' => 0,
        ...$overrides,
    ];
}

it('creates a EUR bank transfer account', function (): void {
    $this->actingAs(accountAdmin())
        ->postJson('/data/admin/payment-accounts', validAccountPayload())
        ->assertCreated()
        ->assertJsonPath('method', PaymentMethod::BankTransfer->value)
        ->assertJsonPath('currency', 'EUR')
        ->assertJsonPath('is_default', true);
});

it('creates a USD remitly account alongside the EUR bank account', function (): void {
    $admin = accountAdmin();

    $this->actingAs($admin)->postJson('/data/admin/payment-accounts', validAccountPayload())->assertCreated();
    $this->actingAs($admin)->postJson('/data/admin/payment-accounts', validAccountPayload([
        'method' => PaymentMethod::Remitly->value,
        'label' => 'Remitly USD',
        'currency' => 'USD',
        'bank_name' => null,
        'iban' => null,
        'bic' => null,
        'holder_email' => 'argenis692@gmail.com',
    ]))->assertCreated();

    expect(PaymentAccountEloquentModel::query()->count())->toBe(2);
});

it('keeps one default per currency instead of one default overall', function (): void {
    $admin = accountAdmin();

    $eur = PaymentAccountEloquentModel::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->postJson('/data/admin/payment-accounts', validAccountPayload([
        'method' => PaymentMethod::Remitly->value,
        'label' => 'Remitly USD',
        'currency' => 'USD',
        'bank_name' => null,
        'iban' => null,
        'bic' => null,
        'holder_email' => 'argenis692@gmail.com',
        'is_default' => true,
    ]))->assertCreated();

    // The USD default must NOT demote the EUR default: they answer different questions.
    expect($eur->refresh()->is_default)->toBeTrue()
        ->and(PaymentAccountEloquentModel::query()->where('currency', 'USD')->firstOrFail()->is_default)
        ->toBeTrue();
});

it('demotes the previous default within the same currency', function (): void {
    $admin = accountAdmin();
    $first = PaymentAccountEloquentModel::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->postJson('/data/admin/payment-accounts', validAccountPayload([
        'label' => 'Revolut EUR',
        'iban' => 'PT50000200000000000000001',
        'is_default' => true,
    ]))->assertCreated();

    expect($first->refresh()->is_default)->toBeFalse();
});

it('rejects an account with no way to receive money', function (): void {
    $this->actingAs(accountAdmin())
        ->postJson('/data/admin/payment-accounts', validAccountPayload([
            'iban' => null,
            'account_number' => null,
            'holder_email' => null,
            'holder_phone' => null,
        ]))
        ->assertJsonValidationErrors('iban');
});

it('rejects an unknown payment method', function (): void {
    $this->actingAs(accountAdmin())
        ->postJson('/data/admin/payment-accounts', validAccountPayload(['method' => 'BITCOIN']))
        ->assertJsonValidationErrors('method');
});

it('lists accounts as a paginated envelope filtered by currency', function (): void {
    $admin = accountAdmin();
    PaymentAccountEloquentModel::factory()->create(['user_id' => $admin->id]);
    PaymentAccountEloquentModel::factory()->remitlyUsd()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/data/admin/payment-accounts')
        ->assertOk()
        ->assertJsonPath('total', 2);

    $this->actingAs($admin)
        ->getJson('/data/admin/payment-accounts?currency=USD')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.label', 'Remitly USD');
});

it('filters the list by settlement method', function (): void {
    $admin = accountAdmin();
    PaymentAccountEloquentModel::factory()->create(['user_id' => $admin->id]);
    PaymentAccountEloquentModel::factory()->remitlyUsd()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/data/admin/payment-accounts?method='.PaymentMethod::Remitly->value)
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.method', PaymentMethod::Remitly->value);
});

it('soft deletes and restores an account', function (): void {
    $admin = accountAdmin();
    $account = PaymentAccountEloquentModel::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->deleteJson("/data/admin/payment-accounts/{$account->uuid}")
        ->assertNoContent();

    expect(PaymentAccountEloquentModel::query()->whereKey($account->id)->exists())->toBeFalse();

    $this->actingAs($admin)
        ->patchJson("/data/admin/payment-accounts/{$account->uuid}/restore")
        ->assertOk()
        ->assertJsonPath('deleted_at', null);

    expect(PaymentAccountEloquentModel::query()->whereKey($account->id)->exists())->toBeTrue();
});

it('bulk deletes and bulk restores accounts', function (): void {
    $admin = accountAdmin();
    $accounts = PaymentAccountEloquentModel::factory()->count(3)->notDefault()
        ->create(['user_id' => $admin->id]);
    $uuids = $accounts->pluck('uuid')->all();

    $this->actingAs($admin)
        ->postJson('/data/admin/payment-accounts/bulk-delete', ['uuids' => $uuids])
        ->assertOk()
        ->assertJsonPath('deleted', 3);

    $this->actingAs($admin)
        ->postJson('/data/admin/payment-accounts/bulk-restore', ['uuids' => $uuids])
        ->assertOk()
        ->assertJsonPath('restored', 3);

    expect(PaymentAccountEloquentModel::query()->count())->toBe(3);
});

it('masks the account identifier in the excel export', function (): void {
    $admin = accountAdmin();
    PaymentAccountEloquentModel::factory()->create(['user_id' => $admin->id]);

    $response = $this->actingAs($admin)
        ->get('/data/admin/payment-accounts/export?format=csv')
        ->assertOk();

    // The spreadsheet leaves the app — it must not carry a usable IBAN.
    expect($response->streamedContent())
        ->not->toContain('PT50003600119910006305349')
        ->toContain('5349');
});

it('streams the accounts as a pdf export', function (): void {
    $admin = accountAdmin();
    PaymentAccountEloquentModel::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->get('/data/admin/payment-accounts/export?format=pdf')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('never writes settlement credentials to the activity log', function (): void {
    $admin = accountAdmin();
    $account = PaymentAccountEloquentModel::factory()->create(['user_id' => $admin->id]);

    // Change a logged field AND a credential in the same request.
    $this->actingAs($admin)->putJson("/data/admin/payment-accounts/{$account->uuid}", validAccountPayload([
        'label' => 'Montepio EUR (principal)',
        'iban' => 'PT50000200000000000000001',
    ]))->assertOk();

    $logged = Activity::query()
        ->where('subject_type', $account->getMorphClass())
        ->where('subject_id', $account->id)
        ->latest('id')
        ->firstOrFail();

    // Guards `logOnly([...])`: switching this model to logAll() would spill the
    // IBAN into the audit trail, and this assertion would catch it.
    expect($logged->log_name)->toBe('payment_accounts.account')
        ->and(json_encode($logged->properties))
        ->not->toContain('PT50000200000000000000001')
        ->not->toContain('iban');
});

it('denies access without the payment account permission', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/data/admin/payment-accounts')
        ->assertForbidden();
});

it('requires authentication', function (): void {
    $this->get('/data/admin/payment-accounts')->assertRedirect('/login');
});
