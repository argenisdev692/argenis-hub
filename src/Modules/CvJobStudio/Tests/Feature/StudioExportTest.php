<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Application\Commands\ExportCvVersionHandler;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvStructureEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvVersionEloquentModel;
use Shared\Domain\Ports\WordExportPort;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('r2');
});

function exportAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function exportVersion(User $admin): StudioCvVersionEloquentModel
{
    $structure = StudioCvStructureEloquentModel::query()->create([
        'user_id' => $admin->id,
        'cv_id' => 1,
        'source_text_hash' => hash('sha256', 'cv'),
        'parser_version' => 'v2',
        'parsed_at' => now(),
        'confirmed_at' => now(),
    ]);

    return StudioCvVersionEloquentModel::query()->create([
        'user_id' => $admin->id,
        'cv_id' => 1,
        'structure_id' => $structure->id,
        'purpose' => 'ats_rewrite',
        'language' => 'en',
        'content' => ['sections' => [
            ['heading' => 'Summary', 'bullets' => ['Senior fullstack developer with Laravel and Vue.js experience.']],
            ['heading' => 'Experience', 'bullets' => ['Built APIs serving 1M requests per day.']],
            ['heading' => 'Skills', 'bullets' => ['Laravel', 'Vue.js', 'PostgreSQL']],
        ]],
        'rules_version' => 2,
    ]);
}

it('renders a structural docx through the PhpWord adapter (T-003 spike)', function (): void {
    $admin = exportAdmin();
    $bytes = app(WordExportPort::class)->render([
        'sections' => [
            ['heading' => 'Experience', 'bullets' => ['Built APIs.']],
        ],
    ]);

    // A real Word2007 package: ZIP magic + non-trivial size.
    expect(substr($bytes, 0, 2))->toBe('PK')->and(strlen($bytes))->toBeGreaterThan(1000);
});

it('exports docx and pdf with structural assertions (SC-6)', function (): void {
    $admin = exportAdmin();
    $version = exportVersion($admin);
    $handler = app(ExportCvVersionHandler::class);

    $docx = $handler->handle($version->uuid, 'docx', $admin->id);

    expect($docx->format)->toBe('docx')
        ->and($docx->disk)->toBe('r2')
        ->and($docx->text_extraction_verified)->toBeTrue();

    $pdf = $handler->handle($version->uuid, 'pdf', $admin->id);

    expect($pdf->format)->toBe('pdf')
        ->and($pdf->text_extraction_verified)->toBeTrue()
        ->and($pdf->extracted_char_count)->toBeGreaterThan(0);
});

it('serves the version export over HTTP with verification flag', function (): void {
    $admin = exportAdmin();
    $version = exportVersion($admin);

    $response = $this->actingAs($admin)->getJson("/cv-studio/versions/{$version->uuid}/export?format=pdf")->assertCreated();

    expect($response->json('data.verified'))->toBeTrue();
});

it('refuses rewrite on an unconfirmed structure (RK-6)', function (): void {
    $admin = exportAdmin();

    $structure = StudioCvStructureEloquentModel::query()->create([
        'user_id' => $admin->id,
        'cv_id' => 1,
        'source_text_hash' => hash('sha256', 'cv'),
        'parser_version' => 'v2',
        'parsed_at' => now(),
    ]);

    // No provider is reached: the confirmation gate fires first.
    $this->actingAs($admin)->postJson("/cv-studio/structures/{$structure->uuid}/rewrite")->assertUnprocessable();

    $this->actingAs($admin)->put("/cv-studio/structures/{$structure->uuid}")->assertRedirect();

    expect($structure->refresh()->confirmed_at)->not->toBeNull();
});

it('answers metric questions against a missing audit with 404', function (): void {
    $admin = exportAdmin();

    $this->actingAs($admin)->post('/cv-studio/audits/'.(string) Str::uuid7().'/answers', [
        'answers' => ['How many requests?' => '1M per day'],
    ])->assertNotFound();
});
