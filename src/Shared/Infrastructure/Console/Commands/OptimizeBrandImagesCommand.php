<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Re-encodes the brand masters in `BRAND/` into web-ready assets in `public/img/`.
 *
 * The files under `BRAND/` are design masters: full size, losslessly encoded,
 * and far too heavy to serve. `hero-glow.webp` alone is 640 KB, and it is the
 * site-wide background painted on every page — the one asset where weight is
 * least affordable.
 *
 * Nothing here edits a master. The masters stay the source of truth; the files
 * in `public/img/` are derived artefacts this command regenerates, which is why
 * it is safe to run on every build.
 *
 * Uses the GD driver rather than Imagick: Herd ships GD with WebP support and
 * no Imagick, and these are flat gradients with no colour-profile subtleties
 * that would justify the heavier dependency.
 */
final class OptimizeBrandImagesCommand extends Command
{
    protected $signature = 'brand:optimize-images
        {--force : Re-encode even when the artefact is already newer than its master}
        {--quality= : Override the per-asset WebP quality (0-100)}';

    protected $description = 'Re-encode the BRAND/ masters into web-ready assets in public/img/.';

    /**
     * The assets to derive.
     *
     * `max_width` is a ceiling, never an upscale — a master narrower than this
     * is re-encoded at its own size. Quality is per-asset because a diffuse
     * glow tolerates far more compression than a logo would.
     *
     * @var list<array{source: string, target: string, max_width: int, quality: int}>
     */
    private const ASSETS = [
        [
            'source' => 'BRAND/hero-glow.webp',
            'target' => 'public/img/hero-glow.webp',
            // The site-wide ambient layer: a soft radial glow with no edges to
            // preserve, drawn at low opacity behind a readability veil. It is
            // the ideal candidate for aggressive lossy encoding.
            'max_width' => 1440,
            'quality' => 68,
        ],
    ];

    public function handle(): int
    {
        if (! $this->hasWebpSupport()) {
            $this->components->error('GD is missing WebP support, so no asset can be encoded.');

            return self::FAILURE;
        }

        $manager = new ImageManager(new Driver);
        $quality = $this->qualityOverride();
        $force = (bool) $this->option('force');

        $savedBytes = 0;
        $failed = false;

        foreach (self::ASSETS as $asset) {
            $result = $this->process($manager, $asset, $quality, $force);

            if ($result === null) {
                $failed = true;

                continue;
            }

            $savedBytes += $result;
        }

        if ($failed) {
            return self::FAILURE;
        }

        if ($savedBytes > 0) {
            $this->newLine();
            $this->components->info(sprintf('Saved %s in total.', $this->humanize($savedBytes)));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{source: string, target: string, max_width: int, quality: int}  $asset
     * @return int|null bytes saved, or null when the asset could not be produced
     */
    private function process(
        ImageManager $manager,
        array $asset,
        ?int $qualityOverride,
        bool $force,
    ): ?int {
        $source = base_path($asset['source']);
        $target = base_path($asset['target']);

        if (! is_file($source)) {
            $this->components->error(sprintf('Master not found: %s', $asset['source']));

            return null;
        }

        if (! $force && $this->isFresh($source, $target)) {
            $this->components->twoColumnDetail($asset['target'], '<fg=gray>up to date</>');

            return 0;
        }

        try {
            // `decodePath`, not `read()`: Intervention 4.3 replaced the generic
            // reader with typed decoders, and `read()` no longer exists.
            $image = $manager->decodePath($source);
            $sourceBytes = (int) filesize($source);

            // `scaleDown` is a ceiling, so a master already narrower than the
            // cap keeps its own dimensions instead of being blown up.
            $image->scaleDown(width: $asset['max_width']);

            $encoded = $image->encode(
                new WebpEncoder(quality: $qualityOverride ?? $asset['quality']),
            );

            $this->ensureDirectoryExists(dirname($target));
            $encoded->save($target);
        } catch (Throwable $e) {
            $this->components->error(sprintf('%s — %s', $asset['target'], $e->getMessage()));

            return null;
        }

        clearstatcache(true, $target);
        $targetBytes = (int) filesize($target);

        $this->components->twoColumnDetail(
            $asset['target'],
            sprintf(
                '%s → <options=bold>%s</> (-%d%%)',
                $this->humanize($sourceBytes),
                $this->humanize($targetBytes),
                $sourceBytes > 0 ? (int) round((1 - $targetBytes / $sourceBytes) * 100) : 0,
            ),
        );

        return max(0, $sourceBytes - $targetBytes);
    }

    /**
     * An artefact is fresh when it exists and is not older than its master.
     *
     * Keeps `npm run build` cheap: the encode only runs when the design file
     * has actually changed.
     */
    private function isFresh(string $source, string $target): bool
    {
        return is_file($target) && filemtime($target) >= filemtime($source);
    }

    private function qualityOverride(): ?int
    {
        $quality = $this->option('quality');

        if ($quality === null) {
            return null;
        }

        return max(0, min(100, (int) $quality));
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function hasWebpSupport(): bool
    {
        return extension_loaded('gd') && (gd_info()['WebP Support'] ?? false) === true;
    }

    private function humanize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
