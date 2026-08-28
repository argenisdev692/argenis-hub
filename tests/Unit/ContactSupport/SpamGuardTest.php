<?php

declare(strict_types=1);

use Modules\ContactSupport\Domain\Spam\SpamAssessment;
use Modules\ContactSupport\Domain\Spam\SpamGuard;

/**
 * SpamGuard scores the public contact form's content after the honeypot and the
 * per-IP throttle have already run. These are pure, database-free checks of the
 * heuristic weights and the threshold boundary.
 */
function assessContactSubmission(array $overrides = []): SpamAssessment
{
    $data = [
        'firstName' => 'Ada',
        'lastName' => 'Lovelace',
        'email' => 'ada@example.com',
        'phone' => '+14155552671',
        'subject' => 'Landing page enquiry',
        'message' => 'Please send me a quote for a one-page marketing site.',
        ...$overrides,
    ];

    return (new SpamGuard)->assess(
        firstName: $data['firstName'],
        lastName: $data['lastName'],
        email: $data['email'],
        phone: $data['phone'],
        subject: $data['subject'],
        message: $data['message'],
    );
}

it('scores a genuine enquiry as clean', function (): void {
    $assessment = assessContactSubmission();

    expect($assessment->score)->toBe(0)
        ->and($assessment->isSpam)->toBeFalse()
        ->and($assessment->reasons)->toBe([])
        ->and($assessment->reasonsForStorage())->toBeNull();
});

it('clears the threshold on a link flood alone', function (): void {
    $assessment = assessContactSubmission([
        'message' => 'Great site! Visit https://a.example http://b.example www.c.example and https://d.example now.',
    ]);

    // 4 links, 1 free, 3 * 20 = 60.
    expect($assessment->score)->toBe(60)
        ->and($assessment->isSpam)->toBeTrue()
        ->and($assessment->reasons)->toContain('too_many_links');
});

it('scores blocked keywords but caps the hit count at two', function (): void {
    $assessment = assessContactSubmission([
        'subject' => 'casino bitcoin forex viagra',
        'message' => 'Cheap casino and bitcoin and forex and viagra, all in one message here.',
    ]);

    expect($assessment->score)->toBe(50)
        ->and($assessment->isSpam)->toBeFalse()
        ->and($assessment->reasons)->toContain('blocked_keyword');
});

it('flags a throw-away inbox', function (): void {
    $assessment = assessContactSubmission(['email' => 'burner@mailinator.com']);

    expect($assessment->reasons)->toContain('disposable_email')
        ->and($assessment->score)->toBe(25);
});

it('flags a subdomain of a throw-away inbox', function (): void {
    expect(assessContactSubmission(['email' => 'x@inbox.mailinator.com'])->reasons)
        ->toContain('disposable_email');
});

it('flags shouting', function (): void {
    $assessment = assessContactSubmission([
        'message' => 'BUY NOW CHEAP DEALS LIMITED STOCK CALL US TODAY FOR THE BEST PRICE EVER',
    ]);

    expect($assessment->reasons)->toContain('excessive_caps')
        ->and($assessment->score)->toBe(15);
});

it('flags a character flood', function (): void {
    expect(assessContactSubmission(['message' => 'Please help meeeeeeeeee with my order right away.'])->reasons)
        ->toContain('character_repetition');
});

it('flags a link smuggled into the name field', function (): void {
    expect(assessContactSubmission(['lastName' => 'visit http://cheap-pills.example'])->reasons)
        ->toContain('name_contains_link');
});

it('flags a gibberish phone number', function (): void {
    expect(assessContactSubmission(['phone' => '+10000000000'])->reasons)
        ->toContain('suspicious_phone');
});

it('clamps a stacked spam payload to 100', function (): void {
    $assessment = assessContactSubmission([
        'email' => 'burner@guerrillamail.com',
        'subject' => 'SEO services and buy backlinks',
        'message' => 'Cheap SEO services, buy backlinks: https://a.example https://b.example https://c.example https://d.example https://e.example',
    ]);

    expect($assessment->score)->toBe(100)
        ->and($assessment->isSpam)->toBeTrue();
});

it('respects a custom threshold', function (): void {
    $guard = new SpamGuard(threshold: 10);

    $assessment = $guard->assess(
        firstName: 'Ada',
        lastName: 'Lovelace',
        email: 'burner@mailinator.com',
        phone: '+14155552671',
        subject: 'Hi',
        message: 'Please send me a quote for a one-page marketing site.',
    );

    expect($assessment->score)->toBe(25)
        ->and($assessment->isSpam)->toBeTrue();
});
