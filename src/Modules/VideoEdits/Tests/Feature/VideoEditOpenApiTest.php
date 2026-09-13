<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('documents every video edit endpoint in its own OpenAPI document', function (): void {
    $path = storage_path('framework/testing/api-video-edits.json');
    File::ensureDirectoryExists(dirname($path));

    $this->artisan('scramble:export', ['--api' => 'video-edits', '--path' => $path])->assertSuccessful();

    /** @var array{paths: array<string, array<string, array{parameters?: list<array{name: string}>}>>} $document */
    $document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    File::delete($path);

    expect(array_keys($document['paths']))->toEqualCanonicalizing([
        '/', '/export', '/{uuid}', '/{uuid}/download-url', '/{uuid}/submit', '/{uuid}/retry', '/{uuid}/report',
    ])
        ->and(array_column($document['paths']['/export']['get']['parameters'] ?? [], 'name'))
        ->toContain('format', 'status', 'date_from', 'date_to');
});
