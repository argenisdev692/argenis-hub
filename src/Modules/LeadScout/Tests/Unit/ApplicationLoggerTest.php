<?php

declare(strict_types=1);

use Modules\LeadScout\Infrastructure\Logging\ApplicationLogger;

it('redacts secrets, PII, draft bodies and connection strings', function (): void {
    $clean = ApplicationLogger::redact([
        'company' => 'some-uuid',
        'score' => 87,
        'password' => 'hunter2',
        'draft_body' => 'Dear Ana, buy now',
        'published_email' => 'ana@example.es',
        'note' => 'wrote to ana@example.es today',
        'dsn' => 'pgsql://postgres:secret@db.supabase.co:5432/postgres',
        'nested' => ['token' => 'abc', 'tier' => 'A'],
    ]);

    expect($clean['company'])->toBe('some-uuid')
        ->and($clean['score'])->toBe(87)
        ->and($clean['password'])->toBe('[redacted]')
        ->and($clean['draft_body'])->toBe('[redacted]')
        ->and($clean['published_email'])->toBe('[redacted]')
        ->and($clean['note'])->toBe('wrote to [email] today')
        ->and($clean['dsn'])->toBe('[redacted]')
        ->and($clean['nested'])->toBe(['token' => '[redacted]', 'tier' => 'A']);
});
