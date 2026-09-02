<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Products\Domain\Enums\ProductStatus;
use Modules\Products\Domain\Enums\ProductType;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;
use Shared\Domain\Enums\BillingUnit;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function productAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

/**
 * @return array<string, mixed>
 */
function validProductPayload(array $overrides = []): array
{
    return [
        'type' => ProductType::Course->value,
        'title' => 'GitHub Copilot para Desarrolladores Web',
        'description' => "Formacion completa en 8 sesiones:\n- Sesion 1 (11 Nov): 3 horas",
        'price' => 52.00,
        'currency' => 'EUR',
        'default_unit' => BillingUnit::Hour->value,
        'status' => ProductStatus::Published->value,
        'level' => 'intermediate',
        'language' => 'es',
        'total_hours' => 25,
        'total_sessions' => 8,
        'modality' => 'online',
        ...$overrides,
    ];
}

it('creates a course product with a derived slug', function (): void {
    $this->actingAs(productAdmin())
        ->postJson('/data/admin/products', validProductPayload())
        ->assertCreated()
        ->assertJsonPath('type', ProductType::Course->value)
        ->assertJsonPath('slug', 'github-copilot-para-desarrolladores-web');

    $product = ProductEloquentModel::query()->firstOrFail();

    expect($product->default_unit)->toBe(BillingUnit::Hour)
        ->and($product->status)->toBe(ProductStatus::Published)
        ->and((float) $product->total_hours)->toBe(25.0);
});

it('creates a video course product', function (): void {
    $this->actingAs(productAdmin())
        ->postJson('/data/admin/products', validProductPayload([
            'type' => ProductType::VideoCourse->value,
            'title' => 'Pildoras de Video Microsoft 365 Copilot',
            'total_hours' => 6,
            'total_sessions' => null,
        ]))
        ->assertCreated();

    expect(ProductEloquentModel::query()->firstOrFail()->type)->toBe(ProductType::VideoCourse);
});

it('appends a suffix when the derived slug is already taken', function (): void {
    $admin = productAdmin();

    $this->actingAs($admin)->postJson('/data/admin/products', validProductPayload())->assertCreated();
    $this->actingAs($admin)->postJson('/data/admin/products', validProductPayload())->assertCreated();

    expect(ProductEloquentModel::query()->orderBy('id')->pluck('slug')->all())->toBe([
        'github-copilot-para-desarrolladores-web',
        'github-copilot-para-desarrolladores-web-2',
    ]);
});

it('keeps the slug unique against soft-deleted rows', function (): void {
    $admin = productAdmin();

    $this->actingAs($admin)->postJson('/data/admin/products', validProductPayload())->assertCreated();
    $trashed = ProductEloquentModel::query()->firstOrFail();
    $this->actingAs($admin)->deleteJson("/data/admin/products/{$trashed->uuid}")->assertNoContent();

    $this->actingAs($admin)->postJson('/data/admin/products', validProductPayload())->assertCreated();

    expect(ProductEloquentModel::query()->firstOrFail()->slug)
        ->toBe('github-copilot-para-desarrolladores-web-2');
});

it('rejects an unknown product type', function (): void {
    $this->actingAs(productAdmin())
        ->postJson('/data/admin/products', validProductPayload(['type' => 'PODCAST']))
        ->assertJsonValidationErrors('type');
});

it('rejects an end date before the start date', function (): void {
    $this->actingAs(productAdmin())
        ->postJson('/data/admin/products', validProductPayload([
            'start_date' => '2026-03-10',
            'end_date' => '2026-03-01',
        ]))
        ->assertJsonValidationErrors('end_date');
});

it('lists products as a paginated envelope the table can read', function (): void {
    $admin = productAdmin();
    ProductEloquentModel::factory()->count(3)->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/data/admin/products?per_page=2')
        ->assertOk()
        ->assertJsonPath('total', 3)
        ->assertJsonPath('per_page', 2)
        ->assertJsonCount(2, 'data');
});

it('filters the list by search term and catalog status', function (): void {
    $admin = productAdmin();
    ProductEloquentModel::factory()->create([
        'user_id' => $admin->id, 'title' => 'Tabnine AI', 'slug' => 'tabnine-ai',
    ]);
    ProductEloquentModel::factory()->draft()->create([
        'user_id' => $admin->id, 'title' => 'Copilot Draft', 'slug' => 'copilot-draft',
    ]);

    $this->actingAs($admin)
        ->getJson('/data/admin/products?search=Tabnine')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.title', 'Tabnine AI');

    $this->actingAs($admin)
        ->getJson('/data/admin/products?product_status=DRAFT')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.title', 'Copilot Draft');
});

it('updates a product', function (): void {
    $admin = productAdmin();
    $product = ProductEloquentModel::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/data/admin/products/{$product->uuid}", validProductPayload(['price' => 60]))
        ->assertOk()
        ->assertJsonPath('price', 60);

    expect((float) $product->refresh()->price)->toBe(60.0);
});

it('soft deletes and restores a product', function (): void {
    $admin = productAdmin();
    $product = ProductEloquentModel::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->deleteJson("/data/admin/products/{$product->uuid}")->assertNoContent();
    expect(ProductEloquentModel::query()->whereKey($product->id)->exists())->toBeFalse();

    $this->actingAs($admin)
        ->patchJson("/data/admin/products/{$product->uuid}/restore")
        ->assertOk()
        ->assertJsonPath('deleted_at', null);

    expect(ProductEloquentModel::query()->whereKey($product->id)->exists())->toBeTrue();
});

it('bulk deletes and bulk restores products', function (): void {
    $admin = productAdmin();
    $products = ProductEloquentModel::factory()->count(3)->create(['user_id' => $admin->id]);
    $uuids = $products->pluck('uuid')->all();

    $this->actingAs($admin)
        ->postJson('/data/admin/products/bulk-delete', ['uuids' => $uuids])
        ->assertOk()
        ->assertJsonPath('deleted', 3);

    expect(ProductEloquentModel::query()->count())->toBe(0);

    $this->actingAs($admin)
        ->postJson('/data/admin/products/bulk-restore', ['uuids' => $uuids])
        ->assertOk()
        ->assertJsonPath('restored', 3);

    expect(ProductEloquentModel::query()->count())->toBe(3);
});

it('streams the catalog as an excel export', function (): void {
    $admin = productAdmin();
    ProductEloquentModel::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->get('/data/admin/products/export?format=xlsx')
        ->assertOk()
        ->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
});

it('streams the catalog as a pdf export', function (): void {
    $admin = productAdmin();
    ProductEloquentModel::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->get('/data/admin/products/export?format=pdf')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('rejects an unknown export format', function (): void {
    $this->actingAs(productAdmin())
        ->get('/data/admin/products/export?format=docx')
        ->assertStatus(422);
});

it('denies access without the products permission', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/data/admin/products')
        ->assertForbidden();
});

it('requires authentication', function (): void {
    $this->get('/data/admin/products')->assertRedirect('/login');
});
