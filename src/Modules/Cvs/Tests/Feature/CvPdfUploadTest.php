<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Cvs\Domain\Enums\CvFileType;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('r2');
});

function pdfUploadAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function uploadPdfBytes(string $visibleText): string
{
    $pdf = "%PDF-1.4\n";
    $o = [];
    $o[1] = strlen($pdf);
    $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $o[2] = strlen($pdf);
    $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $o[3] = strlen($pdf);
    $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
    $stream = "BT /F1 12 Tf 72 720 Td ({$visibleText}) Tj ET";
    $o[4] = strlen($pdf);
    $pdf .= '4 0 obj'."\n<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream\nendobj\n";
    $o[5] = strlen($pdf);
    $pdf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $x = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";

    foreach ([1, 2, 3, 4, 5] as $i) {
        $pdf .= sprintf('%010d 00000 n '."\n", $o[$i]);
    }

    return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$x."\n%%EOF";
}

it('extracts raw_text from an uploaded pdf and stores the file on r2', function (): void {
    $admin = pdfUploadAdmin();
    $file = UploadedFile::fake()->createWithContent('resume.pdf', uploadPdfBytes('Senior Laravel Developer'));

    $this->actingAs($admin)
        ->post('/cvs', [
            'title' => 'My PDF CV',
            'niche' => 'fullstack',
            'is_primary' => true,
            'file' => $file,
        ])
        ->assertRedirect();

    $cv = CvEloquentModel::query()->where('title', 'My PDF CV')->firstOrFail();

    expect($cv->file_type)->toBe(CvFileType::Pdf)
        ->and($cv->raw_text)->toContain('Senior Laravel Developer');

    Storage::disk('r2')->assertExists($cv->file_path);
});

it('accepts a pdf whose mime type is sniffed from real bytes', function (): void {
    // UploadedFile::fake() reports the MIME of the extension, bypassing the
    // finfo sniffing production performs. A real temp file forces the
    // validator (mimetypes + extensionMatchesContent) to read the bytes,
    // exactly like a browser upload.
    $path = tempnam(sys_get_temp_dir(), 'cv');
    file_put_contents($path, uploadPdfBytes('Staff Platform Engineer'));
    $file = new UploadedFile($path, 'resume.pdf', null, null, true);

    $this->actingAs(pdfUploadAdmin())
        ->post('/cvs', [
            'title' => 'Sniffed PDF CV',
            'niche' => 'fullstack',
            'is_primary' => false,
            'file' => $file,
        ])
        ->assertRedirect();

    $cv = CvEloquentModel::query()->where('title', 'Sniffed PDF CV')->firstOrFail();

    expect($cv->file_type)->toBe(CvFileType::Pdf)
        ->and($cv->raw_text)->toContain('Staff Platform Engineer');

    Storage::disk('r2')->assertExists($cv->file_path);

    @unlink($path);
});

it('accepts markdown bytes sniffed as text/plain', function (): void {
    // finfo reports most .md uploads as text/plain (no OS mapping for the
    // extension); the mimetypes allowlist must keep accepting them.
    $path = tempnam(sys_get_temp_dir(), 'cv');
    file_put_contents($path, "# Jane Doe\n\nSenior Laravel Developer\n");
    $file = new UploadedFile($path, 'resume.md', null, null, true);

    $this->actingAs(pdfUploadAdmin())
        ->post('/cvs', [
            'title' => 'Sniffed MD CV',
            'niche' => 'other',
            'is_primary' => false,
            'file' => $file,
        ])
        ->assertRedirect();

    expect(CvEloquentModel::query()->where('title', 'Sniffed MD CV')->count())->toBe(1);

    @unlink($path);
});
