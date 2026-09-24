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
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioExportEloquentModel;
use Shared\Domain\Ports\WordExportPort;
use Smalot\PdfParser\Parser as PdfTextParser;

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

function exportTailoredVersion(User $admin): StudioCvVersionEloquentModel
{
    $structure = StudioCvStructureEloquentModel::query()->create([
        'user_id' => $admin->id,
        'cv_id' => 1,
        'source_text_hash' => hash('sha256', 'cv-tailored'),
        'parser_version' => 'v2',
        'parsed_at' => now(),
        'confirmed_at' => now(),
    ]);

    // Tailor shape: summary + skills, no sections — this exported empty
    // before the ATS normalization.
    return StudioCvVersionEloquentModel::query()->create([
        'user_id' => $admin->id,
        'cv_id' => 1,
        'structure_id' => $structure->id,
        'purpose' => 'tailored',
        'language' => 'en',
        'content' => [
            'summary' => 'Senior fullstack developer with Laravel and Vue.js experience.',
            'skills' => ['Laravel', 'Vue.js', 'PostgreSQL'],
        ],
        'rules_version' => 2,
    ]);
}

function exportRewriteVersion(User $admin): StudioCvVersionEloquentModel
{
    $structure = StudioCvStructureEloquentModel::query()->create([
        'user_id' => $admin->id,
        'cv_id' => 1,
        'source_text_hash' => hash('sha256', 'cv-rewrite'),
        'parser_version' => 'v2',
        'parsed_at' => now(),
        'confirmed_at' => now(),
    ]);

    // Rewrite shape: a bare section list, no `sections` wrapper.
    return StudioCvVersionEloquentModel::query()->create([
        'user_id' => $admin->id,
        'cv_id' => 1,
        'structure_id' => $structure->id,
        'purpose' => 'ats_rewrite',
        'language' => 'en',
        'content' => [
            ['heading' => 'Experience', 'bullets' => ['Built APIs serving 1M requests per day.']],
        ],
        'rules_version' => 2,
    ]);
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

    $docx = StudioExportEloquentModel::query()->where('uuid', $handler->handle($version->uuid, 'docx', $admin->id)['uuid'])->sole();

    expect($docx->format)->toBe('docx')
        ->and($docx->disk)->toBe('r2')
        ->and($docx->text_extraction_verified)->toBeTrue();

    $pdf = StudioExportEloquentModel::query()->where('uuid', $handler->handle($version->uuid, 'pdf', $admin->id)['uuid'])->sole();

    expect($pdf->format)->toBe('pdf')
        ->and($pdf->text_extraction_verified)->toBeTrue()
        ->and($pdf->extracted_char_count)->toBeGreaterThan(0);
});

it('serves the version export over HTTP with verification flag', function (): void {
    $admin = exportAdmin();
    $version = exportVersion($admin);

    Storage::disk('r2')->buildTemporaryUrlsUsing(
        fn (string $path, DateTimeInterface $expiresAt): string => "https://r2.test/{$path}?expires={$expiresAt->getTimestamp()}",
    );

    $response = $this->actingAs($admin)->getJson("/cv-studio/versions/{$version->uuid}/export?format=pdf")->assertCreated();

    // The page follows a short-lived signed URL to the stored file — the
    // response itself is JSON, never the document (OWASP §15).
    expect($response->json('data.verified'))->toBeTrue()
        ->and($response->json('data.download_url'))->toStartWith("https://r2.test/studio-exports/{$version->uuid}.pdf");
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

it('exports a tailored version with ATS contact header and skills line', function (): void {
    $admin = exportAdmin();
    $version = exportTailoredVersion($admin);
    $handler = app(ExportCvVersionHandler::class);
    $header = ['name' => 'Ada Lovelace', 'lines' => ['ada@example.com']];

    $pdf = $handler->handle($version->uuid, 'pdf', $admin->id, $header);
    $pdfText = (string) (new PdfTextParser)->parseContent((string) Storage::disk('r2')->get($pdf['path']))->getText();

    expect($pdf['verified'])->toBeTrue()
        ->and($pdfText)->toContain('Ada Lovelace')
        ->and($pdfText)->toContain('ada@example.com')
        ->and($pdfText)->toContain('Professional Summary')
        ->and($pdfText)->toContain('Core Skills')
        ->and($pdfText)->toContain('Laravel');

    $docx = $handler->handle($version->uuid, 'docx', $admin->id, $header);
    $archive = new ZipArchive;
    $temporary = tempnam(sys_get_temp_dir(), 'ats').'.docx';
    file_put_contents($temporary, (string) Storage::disk('r2')->get($docx['path']));
    $archive->open($temporary);
    $document = (string) $archive->getFromName('word/document.xml');
    $archive->close();
    @unlink($temporary);

    expect(substr((string) Storage::disk('r2')->get($docx['path']), 0, 2))->toBe('PK')
        ->and($document)->toContain('Ada Lovelace')
        ->and($document)->toContain('Laravel');
});

it('exports a rewrite bare-list version without a header', function (): void {
    $admin = exportAdmin();
    $version = exportRewriteVersion($admin);
    $handler = app(ExportCvVersionHandler::class);

    $pdf = $handler->handle($version->uuid, 'pdf', $admin->id);
    $pdfText = (string) (new PdfTextParser)->parseContent((string) Storage::disk('r2')->get($pdf['path']))->getText();

    expect($pdf['verified'])->toBeTrue()
        ->and($pdfText)->toContain('Experience')
        ->and($pdfText)->toContain('1M requests');
});
