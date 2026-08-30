<?php

declare(strict_types=1);

namespace Modules\Post\Application\Commands;

use Modules\Post\Application\DTOs\GenerateContentVariantData;
use Modules\Post\Application\DTOs\ReelPackageData;
use Modules\Post\Domain\Ports\ReelPackageGeneratorPort;
use Shared\Domain\Ports\AuditPort;

/**
 * Imperative AI action (no DB write) — mirrors {@see GeneratePostContentHandler},
 * including the meta-audit of who triggered the (billed) generation. This one
 * is the most expensive call (LLM + ElevenLabs + storage), so the audit trail
 * also records whether the voiceover actually synthesized.
 * Authorization (permission:CREATE_POSTS) is enforced at the route.
 */
final readonly class GenerateReelPackageHandler
{
    public function __construct(
        private ReelPackageGeneratorPort $generator,
        private AuditPort $audit,
    ) {}

    public function handle(GenerateContentVariantData $data, ?object $causer = null): ReelPackageData
    {
        $reel = $this->generator->generateReelPackage($data, $causer);

        $this->audit->log(
            event: 'post.ai.reel_generated',
            properties: [
                'provider' => $data->provider,
                'topic' => $data->topic,
                'angle' => $data->angle,
                'voiceover_generated' => $reel->voiceoverAudioUrl !== null,
            ],
            causer: $causer,
            logName: 'post',
        );

        return $reel;
    }
}
