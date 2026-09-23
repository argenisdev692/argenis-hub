<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Pipeline\Producers;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Ports\CutDecisionProducer;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\Services\FrozenFrameParser;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;
use Modules\VideoEdits\Domain\ValueObjects\SilenceThreshold;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Proposes removing every silence at least as long as the requested threshold
 * (US-2 · AD-7). The speech margin is applied later by the planner (D5).
 */
final readonly class SilenceDecisionProducer implements CutDecisionProducer
{
    public const string NAME = 'silence_detector';

    public function __construct(
        private VideoEditorPort $editor,
        private Config $config,
        private LoggerInterface $logger,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function supports(DecisionContext $context): bool
    {
        return $context->mode->acceptsCutDecisions()
            && ($context->parameters['silence_removal']['enabled'] ?? false) === true;
    }

    public function produce(DecisionContext $context): array
    {
        $threshold = SilenceThreshold::fromSeconds(
            (float) ($context->parameters['silence_removal']['threshold_seconds']
                ?? $this->config->get('video-edit.silence.default_threshold_seconds')),
            (float) $this->config->get('video-edit.silence.min_threshold_seconds'),
            (float) $this->config->get('video-edit.silence.max_threshold_seconds'),
        );

        $silences = $this->editor->detectSilences(
            $context->workingPath,
            $context->workingProbe,
            $threshold,
            (int) $this->config->get('video-edit.silence.noise_floor_db'),
        );

        $frozen = $silences === [] ? null : $this->frozenFrames($context);

        return array_map(
            static fn (TimeRange $silence): CutDecision => new CutDecision(
                producer: self::NAME,
                reason: CutReason::Silence,
                origin: DecisionOrigin::SystemDetection,
                startMs: $silence->startMs,
                endMs: $silence->endMs,
                evidence: array_filter([
                    'threshold_ms' => $threshold->milliseconds,
                    // How much of the silence the screen spent moving; the
                    // planner speeds up a busy wait instead of cutting it.
                    'screen_activity' => $frozen === null ? null : FrozenFrameParser::activityRatio($silence, $frozen),
                ], static fn (int|float|null $value): bool => $value !== null),
            ),
            $silences,
        );
    }

    /**
     * Where the picture stood still, or null when the check is off or failed.
     * Failure is never fatal: without it every silence is simply cut as before.
     *
     * @return list<TimeRange>|null
     */
    private function frozenFrames(DecisionContext $context): ?array
    {
        if (! (bool) $this->config->get('video-edit.screen_activity.enabled', false)) {
            return null;
        }

        try {
            return $this->editor->detectFrozenFrames(
                $context->workingPath,
                $context->workingProbe,
                (int) $this->config->get('video-edit.screen_activity.freeze_noise_db'),
                (float) $this->config->get('video-edit.screen_activity.freeze_min_seconds'),
            );
        } catch (Throwable $exception) {
            $this->logger->warning('video-edit.screen_activity_unavailable', [
                'video_edit_uuid' => $context->videoEditUuid,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }
}
