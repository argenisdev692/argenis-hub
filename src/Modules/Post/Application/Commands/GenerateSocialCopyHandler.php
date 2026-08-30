<?php

declare(strict_types=1);

namespace Modules\Post\Application\Commands;

use Modules\Post\Application\DTOs\GenerateContentVariantData;
use Modules\Post\Application\DTOs\SocialCopyData;
use Modules\Post\Domain\Ports\SocialCopyGeneratorPort;
use Shared\Domain\Ports\AuditPort;

/**
 * Imperative AI action (no DB write) — mirrors {@see GeneratePostContentHandler},
 * including the meta-audit of who triggered the (billed) generation.
 * Authorization (permission:CREATE_POSTS) is enforced at the route.
 */
final readonly class GenerateSocialCopyHandler
{
    public function __construct(
        private SocialCopyGeneratorPort $generator,
        private AuditPort $audit,
    ) {}

    public function handle(GenerateContentVariantData $data, ?object $causer = null): SocialCopyData
    {
        $copy = $this->generator->generateSocialCopy($data, $causer);

        $this->audit->log(
            event: 'post.ai.social_copy_generated',
            properties: ['provider' => $data->provider, 'topic' => $data->topic, 'angle' => $data->angle],
            causer: $causer,
            logName: 'post',
        );

        return $copy;
    }
}
