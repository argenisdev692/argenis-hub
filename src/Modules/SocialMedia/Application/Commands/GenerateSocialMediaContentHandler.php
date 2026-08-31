<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\Commands;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentRepositoryPort;
use Modules\SocialMedia\Domain\Ports\SocialMediaGenerationDispatcherPort;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;
use Shared\Domain\Ports\AuditPort;

/**
 * Step 2 entry point: persists a `generating` placeholder row and hands off
 * to the async quality-loop (see {@see SocialMediaGenerationDispatcherPort}).
 * Unlike Post's synchronous AI-assist handlers, this call returns immediately
 * — the frontend polls/subscribes to the row's status instead of blocking on
 * up to 5 Tavily+AI+image+voice iterations in one HTTP request.
 */
final readonly class GenerateSocialMediaContentHandler
{
    public function __construct(
        private SocialMediaContentRepositoryPort $content,
        private SocialMediaGenerationDispatcherPort $dispatcher,
        private AuditPort $audit,
    ) {}

    #[\NoDiscard]
    public function handle(GenerateSocialMediaContentData $data, Authenticatable $causer): SocialMediaContentEloquentModel
    {
        $content = DB::transaction(fn (): SocialMediaContentEloquentModel => $this->content->create([
            'niche' => $data->niche,
            'topic' => $data->topic,
            'angle' => $data->angle,
            'hook' => $data->hook,
            'key_trend' => $data->keyTrend,
            'audience' => $data->audience,
            'business_goal' => $data->businessGoal,
            'brand_voice' => $data->brandVoice,
            'funnel_stage' => $data->funnelStage,
            'language' => $data->language,
            'provider' => $data->provider,
            'status' => 'generating',
            'created_by' => (int) $causer->getAuthIdentifier(),
        ]));

        $this->dispatcher->dispatch($content->uuid, $data, (int) $causer->getAuthIdentifier());

        $this->audit->log(
            event: 'social_media.ai.generation_started',
            subject: $content,
            properties: [
                'provider' => $data->provider,
                'topic' => $data->topic,
                'funnel_stage' => $data->funnelStage,
                'image_mode' => $data->imageMode->value,
                'generate_voiceover' => $data->generateVoiceover,
            ],
            causer: $causer,
            logName: 'social_media',
        );

        return $content;
    }
}
