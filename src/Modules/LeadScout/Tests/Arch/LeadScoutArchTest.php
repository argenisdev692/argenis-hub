<?php

declare(strict_types=1);

// Architecture guard (spec FR-40, T085): discovery is automatic, sending is
// human — the module ships NO send capability on any channel. Uses the
// stable Pest arch API (unchanged in Pest 5).

arch('no mail or notification capability')
    ->expect('Modules\LeadScout')
    ->not->toUse([
        'Illuminate\Mail\Mailable',
        'Illuminate\Support\Facades\Mail',
        'Illuminate\Notifications\Notification',
        'Illuminate\Notifications\Messages\MailMessage',
        'Symfony\Component\Mailer\MailerInterface',
        'Symfony\Component\Mime\Email',
    ]);

it('never references professional networks outside the guard, search and detector', function (): void {
    // OutreachChannel holds the `linkedin_manual` manual-send medium (FR-41):
    // recording a manual send is not automation.
    $allowed = ['OutboundUrlGuard.php', 'TavilySearchAdapter.php', 'ContactChannelDetector.php', 'DecisionMakerExtractor.php', 'OutreachChannel.php'];
    $offenders = [];

    foreach (leadScoutPhpFiles() as $file) {
        $content = (string) file_get_contents($file);

        if (preg_match('/linkedin/i', $content) === 1 && ! in_array(basename($file), $allowed, true)) {
            $offenders[] = basename($file);
        }
    }

    expect($offenders)->toBeEmpty();
});

it('posts http only to the firecrawl extraction api', function (): void {
    $offenders = [];

    foreach (leadScoutPhpFiles() as $file) {
        $content = (string) file_get_contents($file);

        if (str_contains($content, 'Http::post') && basename($file) !== 'FirecrawlPageFetcher.php') {
            $offenders[] = basename($file);
        }
    }

    expect($offenders)->toBeEmpty();
});

/**
 * @return list<string>
 */
function leadScoutPhpFiles(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__.'/..', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php' && ! str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Tests'.DIRECTORY_SEPARATOR)) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}
