<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Ai;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Campaigns\Application\DTOs\CampaignDraftData;
use Modules\Campaigns\Application\DTOs\CampaignImageConceptData;
use Modules\Campaigns\Application\DTOs\CampaignVideoPackageData;
use Modules\Campaigns\Application\DTOs\GenerateCampaignData;
use Modules\Campaigns\Application\DTOs\PlatformCampaignContentData;
use Modules\Campaigns\Domain\Ports\CampaignAssetRendererPort;
use Modules\Campaigns\Infrastructure\Broadcasting\CampaignProgressNotifier;
use Shared\Domain\Ports\SpeechSynthesizerPort;
use Shared\Domain\Ports\StoragePort;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\Branding\BrandPalette;
use Throwable;

/**
 * Everything billed by the image and speech providers, in one place, invoked
 * ONCE per generation after the quality loop has picked its winner.
 *
 * This used to run inside {@see LaravelAiCampaignAssistantAdapter} on every
 * iteration: a 5-attempt run rendered up to 15 images (a cover plus one per
 * platform variant, every time) to keep 3. Four fifths of the most expensive
 * calls in the pipeline were made for drafts discarded seconds later. The
 * Reels/Stories voiceover, meanwhile, was never produced at all — the agent
 * wrote a `clean_script` for TTS that nothing ever synthesized.
 *
 * Best-effort by contract (see {@see CampaignAssetRendererPort}): a provider
 * failure logs and yields a null path. By the time this runs the copy is
 * already final, and losing a completed campaign because an image endpoint
 * hiccuped would be a strictly worse outcome than shipping it without artwork.
 */
final readonly class CampaignAssetRenderer implements CampaignAssetRendererPort
{
    public function __construct(
        private AIClientInterface $ai,
        private StoragePort $storage,
        private SpeechSynthesizerPort $speech,
        private CampaignProgressNotifier $progress,
    ) {}

    public function render(
        string $campaignUuid,
        CampaignDraftData $draft,
        GenerateCampaignData $data,
        ?object $causer = null,
    ): CampaignDraftData {
        $this->progress->notify($causer, $campaignUuid, 'rendering', 'Rendering the artwork for the winning ad…', 85);

        /** @var array<string, string|null> $voiceovers keyed by script hash — one TTS call per distinct script */
        $voiceovers = [];
        $platforms = [];

        foreach ($draft->platforms as $key => $platform) {
            $platforms[$key] = $this->renderPlatform($platform, $data, $voiceovers);
        }

        $this->progress->notify($causer, $campaignUuid, 'cover_image', 'Rendering the cover image…', 95);

        $coverPath = $this->renderImage('cover', $draft->coverImageConcept, $data->generateImages);

        return $draft->withRenderedAssets(
            platforms: $platforms,
            coverImagePath: $coverPath,
            coverImageUrl: $this->publicUrl($coverPath),
            coverImagePrompt: $this->describeImagePrompt($draft->coverImageConcept),
        );
    }

    /**
     * @param  array<string, string|null>  $voiceovers
     */
    private function renderPlatform(
        PlatformCampaignContentData $platform,
        GenerateCampaignData $data,
        array &$voiceovers,
    ): PlatformCampaignContentData {
        $imagePath = $this->renderImage($platform->platform, $platform->imageConcept, $data->generateImages);

        return $platform->withRenderedAssets(
            imagePrompt: $this->describeImagePrompt($platform->imageConcept),
            imagePath: $imagePath,
            imageUrl: $this->publicUrl($imagePath),
            videoPackage: $this->renderVoiceover($platform, $voiceovers),
        );
    }

    /**
     * Facebook and Instagram usually ship the SAME clean script for a Reels /
     * Stories package. Keying the result by the script itself means one
     * ElevenLabs call covers both, rather than billing and storing two
     * identical audio files per campaign.
     *
     * @param  array<string, string|null>  $voiceovers
     */
    private function renderVoiceover(PlatformCampaignContentData $platform, array &$voiceovers): ?CampaignVideoPackageData
    {
        $package = $platform->videoPackage;

        if ($package === null || trim($package->cleanScript) === '') {
            return $package;
        }

        $key = md5($package->cleanScript);
        $path = $voiceovers[$key] ??= $this->synthesizeAndStore($package->cleanScript);

        return $package->withVoiceover($path, $this->publicUrl($path));
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
                'campaigns/ai/voiceover/'.Str::uuid7().'.mp3',
                base64_decode($audio['base64'], true) ?: '',
                'public',
            );
        } catch (Throwable $e) {
            Log::warning('campaigns.render.voiceover_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * `generate_images = false` bills nothing at all — the prompt is still
     * echoed onto the DTO by {@see self::describeImagePrompt()} so the user
     * can render the asset externally with the exact palette-locked text the
     * pipeline would have used.
     */
    private function renderImage(string $platform, CampaignImageConceptData $concept, bool $generateImages): ?string
    {
        if (! $generateImages) {
            return null;
        }

        try {
            $image = $this->ai->generateImage(
                $this->describeImagePrompt($concept),
                provider: null,
                size: $this->imageSizeForPlatform($platform),
                quality: 'high',
            );

            $extension = str_contains($image['mime'], 'png') ? 'png' : 'jpg';

            return $this->storage->put(
                'campaigns/ai/'.$platform.'/'.Str::uuid7().'.'.$extension,
                base64_decode($image['base64'], true) ?: '',
                'public',
            );
        } catch (Throwable $e) {
            Log::warning('campaigns.render.image_failed', [
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Wraps the agent's short concept in a deterministic template so every
     * image stays on-brand (dark navy + electric purple) regardless of what
     * the model would otherwise invent — see {@see BrandPalette}.
     */
    private function describeImagePrompt(CampaignImageConceptData $concept): string
    {
        $background = BrandPalette::BACKGROUND;
        $primaryAccent = BrandPalette::PRIMARY_ACCENT;
        $secondaryAccent = BrandPalette::SECONDARY_ACCENT;

        return <<<PROMPT
            Premium tech advertising graphic, dark mode, minimalist, high-end.
            Background: deep navy blue ({$background}) with a subtle gradient
            and soft cinematic lighting from top. Centered composition:
            {$concept->visual}, rendered as a glowing 3D glass-and-neon element
            in electric purple ({$primaryAccent}) with soft accents in
            ({$secondaryAccent}), soft rim light, subtle reflections. Below it,
            one short title in clean bold sans-serif: "{$concept->title}".
            Generous negative space, thin geometric accent lines, faint grid
            texture. Aesthetic: engineered, editorial, Apple-keynote quality.
            Sharp focus, depth of field, 4k. No extra text, no paragraphs, no
            watermark.
            PROMPT;
    }

    /**
     * Meta-native aspect ratios: Instagram feed is square, Facebook feed is
     * landscape, the cover plate follows Facebook.
     */
    private function imageSizeForPlatform(string $platform): string
    {
        return match ($platform) {
            'instagram' => '1:1',
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
            Log::warning('campaigns.render.public_url_failed', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
