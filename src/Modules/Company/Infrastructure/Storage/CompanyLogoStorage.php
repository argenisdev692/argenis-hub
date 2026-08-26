<?php

declare(strict_types=1);

namespace Modules\Company\Infrastructure\Storage;

use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Modules\Company\Domain\Enums\LogoVariant;
use Modules\Company\Domain\Ports\CompanyLogoStoragePort;
use Shared\Domain\Ports\StoragePort;
use Shared\Infrastructure\Company\CompanyProfile;
use SplFileInfo;
use Throwable;

/**
 * Brand-mark storage over Cloudflare R2.
 *
 * Every upload is decoded and re-encoded to WebP before it is stored, which is
 * doing three jobs at once:
 *
 * 1. **Sanitisation** (OWASP §8). A file that survives a full decode/encode
 *    round-trip through GD is an image. Polyglot payloads and appended archives
 *    do not survive it, and `strip: true` discards the EXIF block along with any
 *    GPS coordinates the operator's phone attached.
 * 2. **Weight.** These marks are painted in every email and on every external
 *    landing page; a 4 MB PNG straight off a designer's export would be served
 *    to every reader.
 * 3. **One format.** Downstream code never has to branch on the extension.
 *
 * Each upload gets a fresh UUIDv7 filename rather than overwriting a stable one:
 * R2 objects are served through a CDN, and reusing the key would leave the old
 * mark cached for as long as the edge decides to keep it. The superseded object
 * is deleted by the handler once the row points at the new key.
 *
 * The GD driver matches `OptimizeBrandImagesCommand` — Herd ships GD with WebP
 * support and no Imagick.
 */
final readonly class CompanyLogoStorage implements CompanyLogoStoragePort
{
    /** Ceiling, never an upscale: a mark narrower than this keeps its own size. */
    private const int MAX_WIDTH = 1024;

    private const int QUALITY = 88;

    public function __construct(private StoragePort $storage) {}

    public function store(LogoVariant $variant, SplFileInfo $file): string
    {
        $encoded = (new ImageManager(new Driver))
            ->decodePath($file->getPathname())
            ->scaleDown(width: self::MAX_WIDTH)
            ->encode(new WebpEncoder(quality: self::QUALITY, strip: true));

        $key = sprintf('%s/%s.webp', $variant->directory(), Str::uuid7());

        // `public`: mail clients and third-party landing pages cannot follow an
        // expiring signed URL, so the brand marks are the one upload class in
        // this project stored world-readable. Nothing private is ever written here.
        return $this->storage->put($key, (string) $encoded, 'public');
    }

    public function delete(?string $key): void
    {
        if ($key === null || $key === '') {
            return;
        }

        try {
            $this->storage->delete($key);
        } catch (Throwable) {
            // The row already points at the replacement, so the visible state is
            // correct. A stranded object costs storage; a thrown exception here
            // would fail an update that has, in every way that matters, succeeded.
        }
    }

    /**
     * @param  array<string, string|null>  $keys
     * @return array<string, string>
     */
    public function urls(array $keys): array
    {
        $urls = [];

        foreach (LogoVariant::cases() as $variant) {
            $urls[$variant->value] = CompanyProfile::logoUrl(
                $keys[$variant->value] ?? null,
                self::fallbackAsset($variant),
            );
        }

        return $urls;
    }

    /**
     * The bundled asset shown until the operator uploads their own.
     */
    private static function fallbackAsset(LogoVariant $variant): string
    {
        return match ($variant) {
            LogoVariant::Logo => CompanyProfile::FALLBACK_LOGO,
            LogoVariant::LogoWhite => CompanyProfile::FALLBACK_LOGO_WHITE,
            LogoVariant::Mark => CompanyProfile::FALLBACK_MARK,
        };
    }
}
