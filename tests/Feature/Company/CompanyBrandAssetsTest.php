<?php

declare(strict_types=1);

use App\Models\CompanyData;
use Shared\Infrastructure\Company\CompanyProfile;

/**
 * The bundled logo files, pinned to the code that names them.
 *
 * This suite exists because of a failure mode nothing else here catches: the
 * three `FALLBACK_*` constants and the invoice header are plain strings, and
 * renaming a file under `public/img` leaves them pointing at nothing. Every
 * consumer degrades quietly rather than throwing — `logoUrl()` still returns a
 * well-formed URL, `bundledAssetDataUri()` returns `''` and the PDF simply
 * omits the header image — so the first report is a customer looking at an
 * invoice with no logo on it.
 *
 * The Vue side references the same three files by literal path in
 * `resources/js/common/brand/BrandLogo.vue`, which no type check can verify;
 * the last case here is what stands in for that.
 */
test('every logo constant resolves to a file that ships in public/img', function (string $relativePath): void {
    expect(public_path($relativePath))->toBeFile();
})->with([
    'logo' => CompanyProfile::FALLBACK_LOGO,
    'white logo' => CompanyProfile::FALLBACK_LOGO_WHITE,
    'mark' => CompanyProfile::FALLBACK_MARK,
    'email logo' => CompanyProfile::EMAIL_FALLBACK_LOGO,
    'email white logo' => CompanyProfile::EMAIL_FALLBACK_LOGO_WHITE,
]);

test('the email logos are png, because Outlook cannot decode webp', function (string $relativePath): void {
    // Asserted on the sniffed type, not the extension: a `.png` that is really
    // a renamed WebP passes every other check here and still breaks Outlook.
    expect(getimagesize(public_path($relativePath))['mime'] ?? null)->toBe('image/png');
})->with([
    'email logo' => CompanyProfile::EMAIL_FALLBACK_LOGO,
    'email white logo' => CompanyProfile::EMAIL_FALLBACK_LOGO_WHITE,
]);

test('the invoice header embeds the dark logo as a webp data uri', function (): void {
    $dataUri = CompanyProfile::invoiceLogoDataUri();

    // dompdf renders with remote fetching off, so the header only appears at
    // all if the bytes are inlined — an empty string here is a blank invoice.
    expect($dataUri)
        ->toStartWith('data:image/webp;base64,')
        ->and(base64_decode(substr($dataUri, strlen('data:image/webp;base64,')), true))
        ->toBe(file_get_contents(public_path(CompanyProfile::FALLBACK_LOGO)));
});

test('dompdf can decode the invoice logo', function (): void {
    // dompdf hands WebP to GD, so the format is only usable when the extension
    // was built with WebP support. Asserting it here means a PHP upgrade that
    // drops it fails the suite instead of the next invoice.
    expect(function_exists('imagecreatefromwebp'))->toBeTrue()
        ->and(@imagecreatefromwebp(public_path(CompanyProfile::FALLBACK_LOGO)))
        ->not->toBeFalse();
});

test('an uploaded webp logo never reaches an email header', function (): void {
    // CompanyLogoStorage re-encodes every upload to WebP, so this is the shape
    // the column takes the moment an operator uploads their own mark. The web
    // and PDF surfaces are free to use it; the email header is not.
    CompanyData::factory()->create([
        'logo_path' => 'company/logo/0198c7d0-0000-7000-8000-000000000000.webp',
        'logo_white_path' => 'company/logo-white/0198c7d0-0000-7000-8000-000000000001.webp',
    ]);

    CompanyProfile::forget();

    expect(CompanyProfile::data())
        ->logo_email_url->toEndWith('/'.CompanyProfile::EMAIL_FALLBACK_LOGO)
        ->logo_email_white_url->toEndWith('/'.CompanyProfile::EMAIL_FALLBACK_LOGO_WHITE);
});

test('an email-safe uploaded logo is preferred over the bundled png', function (): void {
    // The forward path: once an upload arrives in a format Outlook can draw,
    // the operator's own artwork must win without a change to CompanyProfile.
    CompanyData::factory()->create([
        'logo_white_path' => 'company/logo-white/0198c7d0-0000-7000-8000-000000000002.png',
    ]);

    CompanyProfile::forget();

    expect(CompanyProfile::data()['logo_email_white_url'])
        ->not->toEndWith(CompanyProfile::EMAIL_FALLBACK_LOGO_WHITE);
});

test('the wordmark pair the frontend swaps between are both present', function (): void {
    // BrandLogo.vue renders both and lets CSS choose, so a missing half is
    // invisible in whichever theme the developer happens to be running.
    expect(public_path('img/Logo.webp'))->toBeFile()
        ->and(public_path('img/Logo-white.webp'))->toBeFile();
});
