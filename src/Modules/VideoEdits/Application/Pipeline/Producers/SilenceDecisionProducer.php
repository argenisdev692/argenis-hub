<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Pipeline\Producers;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Ports\CutDecisionProducer;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;
use Modules\VideoEdits\Domain\ValueObjects\SilenceThreshold;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;

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
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function supports(DecisionContext $context): bool
    {
        return $context->mode === VideoEditMode::AutoEdit
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

        return array_map(
            static fn (TimeRange $silence): CutDecision => new CutDecision(
                producer: self::NAME,
                reason: CutReason::Silence,
                origin: DecisionOrigin::SystemDetection,
                startMs: $silence->startMs,
                endMs: $silence->endMs,
                evidence: ['threshold_ms' => $threshold->milliseconds],
            ),
            $silences,
        );
    }
}
