<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Ai;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\SocialMedia\Application\DTOs\GeneratedSocialMediaContentData;
use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;
use Modules\SocialMedia\Application\DTOs\ImageConceptData;
use Modules\SocialMedia\Application\DTOs\PlatformContentData;
use Modules\SocialMedia\Domain\Enums\SocialMediaImageMode;
use Modules\SocialMedia\Domain\Ports\SocialMediaAssetRendererPort;
use Modules\SocialMedia\Infrastructure\Broadcasting\SocialMediaProgressNotifier;
use Modules\SocialMedia\Infrastructure\Queue\GenerateSocialMediaContentJob;
use Shared\Domain\Ports\SpeechSynthesizerPort;
use Shared\Domain\Ports\StoragePort;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\Branding\BrandPalette;
use Throwable;

/**
 * Everything billed by the image and speech providers, in one place, invoked
 * ONCE per generation by {@see GenerateSocialMediaContentJob} after the
 * quality loop has picked its winner.
 *
 * This used to run inside {@see LaravelAiSocialMediaAssistantAdapter} on every
 * iteration: a 5-attempt run rendered up to 30 images and 5 voiceovers to keep
 * 6 images and 1 voiceover. Four fifths of the most expensive calls in the
 * pipeline were made for drafts that were discarded seconds later.
 *
 * Best-effort by contract (see {@see SocialMediaAssetRendererPort}): a
 * provider failure logs and yields a null path. By the time this runs the copy
 * is already final, and losing a completed package because an image endpoint
 * hiccuped would be a strictly worse outcome than shipping it without artwork.
 */
final readonly class SocialMediaAssetRenderer implements SocialMediaAssetRendererPort
{
    public function __construct(
        private AIClientInterface $ai,
        private StoragePort $storage,
        private SpeechSynthesizerPort $speech,
        private SocialMediaImagePromptFactory $prompts,
        private SocialMediaProgressNotifier $progress,
    ) {}

    public function render(
        string $contentUuid,
        GeneratedSocialMediaContentData $draft,
        GenerateSocialMediaContentData $data,
        ?object $causer = null,
    ): GeneratedSocialMediaContentData {
        $this->progress->notify($causer, $contentUuid, 'rendering', 'Rendering the artwork for the winning draft…', 80);

        /** @var array<string, string|null> $voiceovers keyed by script hash — one TTS call per distinct script */
        $voiceovers = [];
        $platforms = [];

        foreach ($draft->platforms as $key => $platform) {
            $platforms[$key] = $this->renderPlatform($platform, $data, $voiceovers);
        }

        $this->progress->notify($causer, $contentUuid, 'cover_image', 'Rendering the cover image…', 92);

        $coverPath = $this->renderImage('cover', $draft->coverImageConcept, $data->imageMode);

        return $draft->withCoverAssets(
            platforms: $platforms,
            coverImagePrompt: $this->describeImagePrompt($draft->coverImageConcept, $data->imageMode),
            coverImagePath: $coverPath,
            coverImageUrl: $this->publicUrl($coverPath),
        );
    }

    /**
     * @param  array<string, string|null>  $voiceovers
     */
    private function renderPlatform(
        PlatformContentData $platform,
        GenerateSocialMediaContentData $data,
        array &$voiceovers,
    ): PlatformContentData {
        $imagePath = $this->renderImage($platform->platform, $platform->imageConcept, $data->imageMode);
        $voiceoverPath = $this->renderVoiceover($platform, $data, $voiceovers);

        return $platform->withRenderedAssets(
            imagePrompt: $this->describeImagePrompt($platform->imageConcept, $data->imageMode),
            imagePath: $imagePath,
            imageUrl: $this->publicUrl($imagePath),
            voiceoverAudioPath: $voiceoverPath,
            voiceoverAudioUrl: $this->publicUrl($voiceoverPath),
        );
    }

    /**
     * TikTok and Instagram Reels usually ship the SAME clean script. Keying
     * the result by the script itself means one ElevenLabs call covers both —
     * two identical audio files were being billed and stored per package.
     *
     * @param  array<string, string|null>  $voiceovers
     */
    private function renderVoiceover(
        PlatformContentData $platform,
        GenerateSocialMediaContentData $data,
        array &$voiceovers,
    ): ?string {
        $script = $platform->videoScript();

        if (! $data->generateVoiceover
            || $script === null
            || trim($script) === ''
            || ! in_array($platform->platform, ['tiktok', 'instagram'], true)
        ) {
            return null;
        }

        $key = md5($script);

        return $voiceovers[$key] ??= $this->synthesizeAndStore($script);
    }

    /**
     * Best-effort voiceover. Null on any failure (ElevenLabs unreachable or
     * misconfigured) — the CapCut timeline and clean script remain fully
     * usable without audio.
     */
    private function synthesizeAndStore(string $script): ?string
    {
        try {
            $audio = $this->speech->synthesize($script);

            if ($audio === null) {
                return null;
            }

            return $this->storage->put(
                'social-media/ai/voiceover/'.Str::uuid7().'.mp3',
                base64_decode($audio['base64'], true) ?: '',
                'public',
            );
        } catch (Throwable $e) {
            Log::warning('social_media.render.voiceover_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Renders one asset according to the caller's {@see SocialMediaImageMode}:
     *
     * - `none` — no billed call at all, returns null (the prompt is still
     *   echoed on the DTO by {@see self::describeImagePrompt()}).
     * - `base` — the palette background plate only, whatever route the agent
     *   picked: a plate has no subject to place and no labels to draw, so the
     *   SVG roadmap route would render an empty diagram.
     * - `full` — route A/B via the image model, route C as deterministic SVG.
     *
     * Aspect ratio is platform-native; prompt text comes from
     * {@see SocialMediaImagePromptFactory}, never from the model.
     */
    private function renderImage(string $platform, ImageConceptData $concept, SocialMediaImageMode $mode): ?string
    {
        if (! $mode->rendersImage()) {
            return null;
        }

        try {
            if ($mode->honorsImageRoute() && $concept->route === 'c') {
                return $this->storeSvgRoadmap($platform, $concept);
            }

            $image = $this->ai->generateImage(
                $this->prompts->forMode($mode, $concept->route, $concept->title, $concept->visual),
                provider: null,
                size: $this->imageSizeForPlatform($platform),
                quality: 'high',
            );

            $extension = str_contains($image['mime'], 'png') ? 'png' : 'jpg';

            return $this->storage->put(
                'social-media/ai/'.$platform.'/'.Str::uuid7().'.'.$extension,
                base64_decode($image['base64'], true) ?: '',
                'public',
            );
        } catch (Throwable $e) {
            Log::warning('social_media.render.image_failed', [
                'platform' => $platform,
                'route' => $concept->route,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Route C — crisp SVG roadmap drawn in PHP, because image models mangle
     * multi-label text. Colours come from {@see BrandPalette}, the single
     * source shared with `resources/css/globals.css`.
     */
    private function storeSvgRoadmap(string $platform, ImageConceptData $concept): string
    {
        $steps = $concept->svgSteps !== []
            ? array_slice(array_values(array_filter(array_map('trim', $concept->svgSteps))), 0, 6)
            : ['Step 1', 'Step 2', 'Step 3'];

        $background = BrandPalette::BACKGROUND;
        $primary = BrandPalette::PRIMARY_ACCENT;
        $secondary = BrandPalette::SECONDARY_ACCENT;
        $textPrimary = BrandPalette::TEXT_PRIMARY;
        $textMuted = BrandPalette::TEXT_MUTED;
        $safeTitle = $this->escapeSvg($concept->title);

        $stepCount = count($steps);
        $spacing = $stepCount > 1 ? 900 / ($stepCount - 1) : 0;
        $nodes = '';

        foreach ($steps as $index => $label) {
            $x = 150 + ($index * $spacing);
            $safeLabel = $this->escapeSvg($label);
            $stepNumber = $index + 1;
            $nodes .= <<<SVG
                <circle cx="{$x}" cy="320" r="18" fill="{$primary}" stroke="{$secondary}" stroke-width="3"/>
                <text x="{$x}" y="290" text-anchor="middle" fill="{$textMuted}" font-family="system-ui,sans-serif" font-size="14" font-weight="700">{$stepNumber}</text>
                <text x="{$x}" y="370" text-anchor="middle" fill="{$textPrimary}" font-family="system-ui,sans-serif" font-size="16" font-weight="600">{$safeLabel}</text>
                SVG;
        }

        $lineEnd = 150 + (($stepCount - 1) * $spacing);

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
              <rect width="1200" height="630" fill="{$background}"/>
              <defs>
                <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="{$primary}" stop-opacity="0.25"/>
                  <stop offset="100%" stop-color="{$secondary}" stop-opacity="0.08"/>
                </linearGradient>
              </defs>
              <rect width="1200" height="630" fill="url(#g)"/>
              <text x="600" y="120" text-anchor="middle" fill="{$textPrimary}" font-family="system-ui,sans-serif" font-size="36" font-weight="700">{$safeTitle}</text>
              <line x1="150" y1="320" x2="{$lineEnd}" y2="320" stroke="{$primary}" stroke-width="4" stroke-linecap="round"/>
              {$nodes}
            </svg>
            SVG;

        return $this->storage->put('social-media/ai/'.$platform.'/'.Str::uuid7().'.svg', $svg, 'public');
    }

    /**
     * Labels are model output rendered into markup, so they are escaped as
     * untrusted input — an unescaped `</text><script>` would otherwise ride
     * into a stored, publicly served SVG (OWASP A03).
     */
    private function escapeSvg(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * The prompt echoed back to the client — ALWAYS populated, including under
     * {@see SocialMediaImageMode::None}, so the user can render the asset
     * externally with the exact palette-locked text the pipeline would have
     * used. The `[route x]` marker stays as the human-readable hint of which
     * visual treatment the agent chose.
     */
    private function describeImagePrompt(ImageConceptData $concept, SocialMediaImageMode $mode): string
    {
        $effectiveRoute = $mode->honorsImageRoute() ? $concept->route : 'base';

        return "[route {$effectiveRoute}] ".$this->prompts->forMode($mode, $concept->route, $concept->title, $concept->visual);
    }

    /**
     * Platform-native aspect ratios (LinkedIn / Twitter / Facebook / cover
     * ≈ 16:9, Instagram 1:1, TikTok 9:16).
     */
    private function imageSizeForPlatform(string $platform): string
    {
        return match ($platform) {
            'instagram' => '1:1',
            'tiktok' => '9:16',
            default => '16:9',
        };
    }

    private function publicUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        try {
            return $this->storage->publicUrl($path);
        } catch (Throwable $e) {
            Log::warning('social_media.render.public_url_failed', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
