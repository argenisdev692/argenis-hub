<?php

declare(strict_types=1);

use App\Models\CompanyData;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Shared\Infrastructure\Company\CompanyProfile;
use Spatie\Activitylog\Models\Activity;

/**
 * Brand-mark uploads.
 *
 * Two things carry the weight here and both are asserted directly: every
 * accepted file is re-encoded (which is what makes the upload safe as well as
 * small), and the superseded object is removed only after the row already points
 * at its replacement.
 */
beforeEach(function (): void {
    CompanyProfile::forget();
    $this->seed(RolePermissionSeeder::class);

    Storage::fake('r2');
});

/**
 * @param  list<string>  $permissions
 */
function uploader(array $permissions = ['VIEW_COMPANY_DATA', 'UPDATE_COMPANY_DATA']): User
{
    $user = User::factory()->create();

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function companyRecord(array $attributes = []): CompanyData
{
    return CompanyData::factory()->create([
        'user_id' => User::factory(),
        ...$attributes,
    ]);
}

it('stores an uploaded mark and points the record at it', function (): void {
    $model = companyRecord(['logo_path' => null]);

    $this->actingAs(uploader())
        ->post(route('company.logos.update'), [
            'logo' => UploadedFile::fake()->image('brand.png', 600, 200),
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'company-logos-updated');

    $key = $model->refresh()->logo_path;

    expect($key)->toStartWith('company/logos/logo/')
        ->and($key)->toEndWith('.webp');

    Storage::disk('r2')->assertExists($key);
});

it('re-encodes to webp within the width ceiling, discarding whatever was uploaded', function (): void {
    $model = companyRecord(['mark_path' => null]);

    $this->actingAs(uploader())
        ->post(route('company.logos.update'), [
            'mark' => UploadedFile::fake()->image('mark.jpg', 1600, 800),
        ])
        ->assertRedirect();

    $stored = (string) Storage::disk('r2')->get((string) $model->refresh()->mark_path);

    // Read the dimensions out and release the handle before asserting: an
    // expectation chain keeps whatever it is given alive for the rest of the
    // run, and a decoded bitmap is the one thing worth not keeping.
    $image = imagecreatefromstring($stored);
    $width = $image === false ? 0 : imagesx($image);
    $height = $image === false ? 0 : imagesy($image);
    unset($image);

    expect(substr($stored, 8, 4))->toBe('WEBP')
        ->and($width)->toBe(1024)
        ->and($height)->toBe(512);
});

it('replaces one mark without disturbing the others', function (): void {
    $model = companyRecord([
        'logo_path' => 'company/logos/logo/old.webp',
        'logo_white_path' => 'company/logos/logo_white/keep.webp',
        'mark_path' => 'company/logos/mark/keep.webp',
    ]);

    Storage::disk('r2')->put('company/logos/logo/old.webp', 'old-bytes');

    $this->actingAs(uploader())
        ->post(route('company.logos.update'), [
            'logo' => UploadedFile::fake()->image('brand.png', 600, 200),
        ])
        ->assertRedirect();

    $model->refresh();

    expect($model->logo_path)->not->toBe('company/logos/logo/old.webp')
        ->and($model->logo_white_path)->toBe('company/logos/logo_white/keep.webp')
        ->and($model->mark_path)->toBe('company/logos/mark/keep.webp');

    // Only once the row points at the replacement is the old object dropped.
    Storage::disk('r2')->assertMissing('company/logos/logo/old.webp');
    Storage::disk('r2')->assertExists((string) $model->logo_path);
});

it('accepts all three marks in one request', function (): void {
    $model = companyRecord();

    $this->actingAs(uploader())
        ->post(route('company.logos.update'), [
            'logo' => UploadedFile::fake()->image('a.png', 240, 240),
            'logo_white' => UploadedFile::fake()->image('b.png', 240, 240),
            'mark' => UploadedFile::fake()->image('c.png', 240, 240),
        ])
        ->assertRedirect();

    $model->refresh();

    expect($model->logo_path)->toStartWith('company/logos/logo/')
        ->and($model->logo_white_path)->toStartWith('company/logos/logo_white/')
        ->and($model->mark_path)->toStartWith('company/logos/mark/');
});

it('returns the refreshed URLs to a JSON client', function (): void {
    companyRecord();

    $this->actingAs(uploader())
        ->postJson(route('company.logos.update'), [
            'logo' => UploadedFile::fake()->image('brand.png', 240, 240),
        ])
        ->assertOk()
        ->assertJsonStructure(['logo', 'logo_white', 'mark']);
});

it('records the replacement in the audit trail', function (): void {
    companyRecord();

    $this->actingAs(uploader())
        ->post(route('company.logos.update'), [
            'logo' => UploadedFile::fake()->image('brand.png', 240, 240),
        ])
        ->assertRedirect();

    $activity = Activity::query()->where('event', 'company.logos_updated')->latest('id')->first();

    expect($activity?->log_name)->toBe('company.data')
        ->and($activity?->getProperty('variants'))->toBe(['logo']);
});

it('rejects an upload that is not an image', function (): void {
    companyRecord();

    $this->actingAs(uploader())
        ->postJson(route('company.logos.update'), [
            'logo' => UploadedFile::fake()->create('payload.pdf', 10, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('logo');
});

it('rejects SVG, which a raster re-encode cannot flatten', function (): void {
    companyRecord();

    $svg = UploadedFile::fake()->createWithContent(
        'brand.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    );

    $this->actingAs(uploader())
        ->postJson(route('company.logos.update'), ['logo' => $svg])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('logo');
});

it('rejects an image past the dimension ceiling', function (): void {
    companyRecord();

    $this->actingAs(uploader())
        ->postJson(route('company.logos.update'), [
            'logo' => UploadedFile::fake()->image('huge.png', 4200, 60),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('logo');
});

it('requires at least one mark', function (): void {
    companyRecord();

    $this->actingAs(uploader())
        ->postJson(route('company.logos.update'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['logo', 'logo_white', 'mark']);
});

it('refuses an operator who may only read', function (): void {
    companyRecord();

    $this->actingAs(uploader(['VIEW_COMPANY_DATA']))
        ->post(route('company.logos.update'), [
            'logo' => UploadedFile::fake()->image('brand.png', 240, 240),
        ])
        ->assertForbidden();

    expect(Storage::disk('r2')->allFiles())->toBeEmpty();
});
