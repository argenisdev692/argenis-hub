<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Ai;

use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Domain\Ports\CvTranslatorPort;
use Shared\Infrastructure\AI\PromptCache\PromptLayer;

/**
 * ES / EN / PT-PT variants through `AiCallExecutor` (T-078): the
 * untranslatable-terms list + version content form the long-lived cached
 * layer, the target language the tail — three languages share one prefix.
 */
final readonly class LaravelAiCvTranslator implements CvTranslatorPort
{
    public function __construct(private AiCallExecutor $calls) {}

    public function translate(array $content, string $language, int $userId): array
    {
        $hash = hash('sha256', json_encode($content, JSON_THROW_ON_ERROR));
        $terms = implode(', ', (array) config('cv-job-studio.untranslatable_terms', []));

        $layer = PromptLayer::long("CV version {$hash}. Untranslatable terms (keep in English): {$terms}.\n".json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $cached = CvSnapshotLayer::cached(AiPurpose::Translate->value, $layer, "Target language: {$language}", $hash);

        $response = $this->calls->call(AiPurpose::Translate, TranslateCvAgent::class, $cached->asSingleMessage(), $userId, null, $cached)['response'];

        return array_values((array) ($response['sections'] ?? []));
    }
}
