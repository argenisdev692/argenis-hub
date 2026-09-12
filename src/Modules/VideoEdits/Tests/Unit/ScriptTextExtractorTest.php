<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Exceptions\ScriptUnreadableException;
use Modules\VideoEdits\Domain\Ports\ScriptTextExtractorPort;
use Modules\VideoEdits\Domain\ValueObjects\ScriptDocument;

/**
 * Written files, tracked so afterEach can remove them. A static holder rather
 * than `$this`: Pest proxies the test instance, and appending to a property
 * through that proxy silently does nothing.
 *
 * @param  string|null  $path  null reads and clears the list
 * @return list<string>
 */
function trackedScripts(?string $path = null): array
{
    static $paths = [];

    if ($path === null) {
        $collected = $paths;
        $paths = [];

        return $collected;
    }

    $paths[] = $path;

    return $paths;
}

afterEach(function (): void {
    foreach (trackedScripts() as $path) {
        @unlink($path);
    }
});

function tempScript(string $contents, string $extension): string
{
    $path = tempnam(sys_get_temp_dir(), 'vescript').'.'.$extension;
    file_put_contents($path, $contents);
    trackedScripts($path);

    return $path;
}

it('keeps markdown structure, which is how a script states its sections', function (): void {
    $path = tempScript(
        "# Video 6.1\n\n## INTRODUCCIÓN (1 minuto)\n\n- Saludo\n\n## CREAR AGENDA (3 minutos)\n",
        'md',
    );

    $document = app(ScriptTextExtractorPort::class)->extract($path, 'Video 6.1.md');

    expect($document)->toBeInstanceOf(ScriptDocument::class)
        ->and($document->fileName)->toBe('Video 6.1.md')
        // The headings and their time budgets are exactly what the topic audit
        // reads, so they must survive extraction.
        ->and($document->text)->toContain('## INTRODUCCIÓN (1 minuto)')
        ->and($document->text)->toContain('## CREAR AGENDA (3 minutos)');
});

it('collapses the ragged whitespace that inflates the token count', function (): void {
    $path = tempScript("Uno    dos\n\n\n\n\ntres", 'md');

    $document = app(ScriptTextExtractorPort::class)->extract($path, 'script.md');

    expect($document->text)->toBe("Uno dos\n\ntres");
});

it('refuses a script with no readable text', function (): void {
    $path = tempScript("   \n\n  ", 'md');

    expect(fn () => app(ScriptTextExtractorPort::class)->extract($path, 'empty.md'))
        ->toThrow(ScriptUnreadableException::class);
});

it('explains an unreadable PDF instead of failing obscurely', function (): void {
    // A scanned or corrupt PDF is the common real case, and the message has to
    // tell the user what to do about it.
    $path = tempScript('not really a pdf', 'pdf');

    expect(fn () => app(ScriptTextExtractorPort::class)->extract($path, 'scanned.pdf'))
        ->toThrow(ScriptUnreadableException::class, 'scanned.pdf');
});

it('truncates a runaway script rather than sending all of it', function (): void {
    $document = new ScriptDocument('big.md', str_repeat('a', 500));

    expect($document->truncated(100)->text)->toHaveLength(100)
        ->and($document->truncated(1_000)->text)->toHaveLength(500);
});
