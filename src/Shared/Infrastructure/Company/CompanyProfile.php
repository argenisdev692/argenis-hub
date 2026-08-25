<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Company;

use App\Models\CompanyData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Modules\Invoices\Infrastructure\Pdf\DomPdfInvoiceRenderer;
use Shared\Domain\Ports\StoragePort;
use Throwable;

/**
 * Single source of company branding for emails, the hero and the navbar.
 *
 * DB-first: reads the main `company_data` row so NO company-specific env vars
 * are required. When the table is empty or unavailable (e.g. before the first
 * migrate) it falls back to the app's own APP_NAME / APP_URL / MAIL_FROM_ADDRESS
 * and the bundled logos shipped in `public/img`. Cached briefly to avoid a query
 * per email/render.
 *
 * Uploaded logos live on Cloudflare R2 (public visibility); the stored column
 * holds the R2 object key, resolved to a permanent public URL via StoragePort.
 * A null column falls back to the bundled asset served from APP_URL. R2 failures
 * degrade to the bundled asset rather than breaking every render.
 *
 * @phpstan-type CompanyArray array{name: string, description: ?string, url: ?string, logo_url: string, logo_white_url: string, mark_url: string, address: ?string, address_2: ?string, zip_code: ?string, city: ?string, state: ?string, country: ?string, country_code: ?string, phone: ?string, support_email: ?string, latitude: ?float, longitude: ?float, socials: array<string, string>}
 */
final class CompanyProfile
{
    private const string CACHE_KEY = 'company.profile';

    private const string FALLBACK_LOGO = 'img/Logo.png';

    private const string FALLBACK_LOGO_WHITE = 'img/Logo-white.png';

    private const string FALLBACK_MARK = 'img/Mark.png';

    /** Invoice PDF header (dompdf) — not the CRM-uploaded company logo. */
    private const string INVOICE_PDF_LOGO = 'img/img-invoice/logo.webp';

    /**
     * @return CompanyArray
     */
    public static function data(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(30), static function (): array {
            try {
                $company = CompanyData::query()->orderBy('id')->first();
            } catch (Throwable) {
                $company = null;
            }

            return [
                'name' => (string) ($company?->company_name ?: config('app.name')),
                // AI context for the Post module's topic ideation (never rendered
                // in emails/hero — only consumed server-side, harmless addition).
                'description' => $company?->description,
                'url' => $company?->website ?: config('app.url'),
                'logo_url' => self::logoUrl($company?->logo_path, self::FALLBACK_LOGO),
                'logo_white_url' => self::logoUrl($company?->logo_white_path, self::FALLBACK_LOGO_WHITE),
                'mark_url' => self::logoUrl($company?->mark_path, self::FALLBACK_MARK),
                'address' => $company?->address,
                'address_2' => $company?->address_2,
                'zip_code' => $company?->zip_code,
                'city' => $company?->city,
                'state' => $company?->state,
                'country' => $company?->country,
                'country_code' => $company?->country_code,
                'phone' => $company?->phone,
                'support_email' => $company?->email ?: config('mail.from.address'),
                'latitude' => $company?->latitude,
                'longitude' => $company?->longitude,
                'socials' => array_filter([
                    'linkedin' => $company?->linkedin_link,
                    'twitter' => $company?->twitter_link,
                    'instagram' => $company?->instagram_link,
                    'facebook' => $company?->facebook_link,
                    'tiktok' => $company?->tiktok_link,
                ]),
            ];
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY.'.pdf');
    }

    /**
     * Base64 `data:` URI for the invoice PDF header only (`public/img/img-invoice`).
     * Does not affect {@see data()}, {@see pdfBranding()}, emails, or the CRM UI.
     */
    public static function invoiceLogoDataUri(): string
    {
        return self::bundledAssetDataUri(self::INVOICE_PDF_LOGO);
    }

    /**
     * Branding payload for the corporate PDF export layout.
     *
     * dompdf renders with remote fetching disabled, so logos are pre-fetched and
     * embedded as base64 `data:` URIs here rather than referenced by URL. The
     * white logo sits on the coloured header band; the dark logo is exposed for
     * light-background contexts. Contact fields feed the header/footer blocks.
     *
     * Single-invoice documents (`exports.pdf.invoice`) do **not** use these logo
     * fields for the header — only {@see invoiceLogoDataUri()} via
     * {@see DomPdfInvoiceRenderer}.
     *
     * @return array{name: string, legal_name: ?string, website: ?string, email: ?string, phone: ?string, address: ?string, country: ?string, country_code: ?string, nif_nipc: ?string, nie: ?string, bank_beneficiary: ?string, bank_iban: ?string, bank_bic: ?string, bank_name: ?string, invoice_notes: ?string, logo_data_uri: string, logo_dark_data_uri: string, socials: array<string, string>}
     */
    public static function pdfBranding(): array
    {
        return Cache::remember(self::CACHE_KEY.'.pdf', now()->addMinutes(30), static function (): array {
            try {
                $company = CompanyData::query()->orderBy('id')->first();
            } catch (Throwable) {
                $company = null;
            }

            return [
                'name' => (string) ($company?->company_name ?: config('app.name')),
                'legal_name' => $company?->name,
                'website' => $company?->website ?: config('app.url'),
                'email' => $company?->email ?: config('mail.from.address'),
                'phone' => $company?->phone,
                'address' => self::composeAddress($company),
                'country' => $company?->country,
                'country_code' => $company?->country_code,
                'nif_nipc' => $company?->nif_nipc,
                'nie' => $company?->nie,
                'bank_beneficiary' => $company?->bank_beneficiary,
                'bank_iban' => $company?->bank_iban,
                'bank_bic' => $company?->bank_bic,
                'bank_name' => $company?->bank_name,
                'invoice_notes' => $company?->invoice_notes,
                'logo_data_uri' => self::logoDataUri($company?->logo_white_path, self::FALLBACK_LOGO_WHITE),
                'logo_dark_data_uri' => self::logoDataUri($company?->logo_path, self::FALLBACK_LOGO),
                'socials' => array_filter([
                    'linkedin' => $company?->linkedin_link,
                    'twitter' => $company?->twitter_link,
                    'instagram' => $company?->instagram_link,
                    'facebook' => $company?->facebook_link,
                    'tiktok' => $company?->tiktok_link,
                ]),
            ];
        });
    }

    /**
     * Default document `<title>` for the guest/hero pages: the DB brand name
     * joined with the env-driven tagline (`config('app.title_description')`).
     */
    public static function documentTitle(): string
    {
        return self::data()['name'].' — '.(string) config('app.title_description');
    }

    /**
     * Resolve a stored logo reference to an absolute URL: an R2 object key →
     * permanent public URL; otherwise the bundled asset served from APP_URL.
     */
    private static function logoUrl(?string $key, string $fallback): string
    {
        if ($key === null || $key === '') {
            return self::bundledUrl($fallback);
        }

        try {
            return app(StoragePort::class)->publicUrl($key);
        } catch (Throwable) {
            return self::bundledUrl($fallback);
        }
    }

    private static function bundledUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * Collapse the stored address columns into a single presentational line for
     * the PDF header. Empty parts are dropped so no dangling commas appear.
     */
    private static function composeAddress(?CompanyData $company): ?string
    {
        if ($company === null) {
            return null;
        }

        $cityLine = trim(implode(' ', array_filter([
            (string) ($company->zip_code ?? ''),
            (string) ($company->city ?? ''),
        ])));

        $parts = array_filter([
            $company->address,
            $cityLine,
            (string) ($company->state ?? ''),
            (string) ($company->country ?? ''),
        ], static fn (?string $part): bool => $part !== null && $part !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Resolve a logo reference to an embeddable base64 `data:` URI. Tries the
     * uploaded object on the cloud disk first, then the bundled public asset,
     * degrading to an empty string so the layout simply omits the image.
     */
    private static function logoDataUri(?string $key, string $fallback): string
    {
        if ($key !== null && $key !== '') {
            try {
                $disk = Storage::disk((string) config('filesystems.cloud', 'r2'));

                if ($disk->exists($key)) {
                    $bytes = $disk->get($key);

                    if ($bytes !== null && $bytes !== '') {
                        $mime = $disk->mimeType($key) ?: 'image/png';

                        return 'data:'.$mime.';base64,'.base64_encode($bytes);
                    }
                }
            } catch (Throwable) {
            }
        }

        try {
            $path = public_path($fallback);

            if (is_file($path)) {
                return self::bundledAssetDataUri($fallback);
            }
        } catch (Throwable) {
        }

        return '';
    }

    private static function bundledAssetDataUri(string $relativePath): string
    {
        try {
            $path = public_path($relativePath);

            if (! is_file($path)) {
                return '';
            }

            $mime = self::mimeForPublicPath($relativePath);

            return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
        } catch (Throwable) {
            return '';
        }
    }

    private static function mimeForPublicPath(string $relativePath): string
    {
        return match (strtolower(pathinfo($relativePath, PATHINFO_EXTENSION))) {
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }
}
