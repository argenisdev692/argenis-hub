<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Tests\Unit;

use Modules\SocialMedia\Domain\Services\ContentQualityEvaluator;
use PHPUnit\Framework\TestCase;

final class ContentQualityEvaluatorTest extends TestCase
{
    public function test_all_pass_when_every_threshold_is_met(): void
    {
        $result = (new ContentQualityEvaluator)->evaluate([
            'human_writing_index' => 75,
            'virality_score' => 70,
            'engagement_score' => 70,
            'roi_score' => 70,
            'trend_alignment' => 70,
        ]);

        $this->assertTrue($result->allPass);
        $this->assertSame([], $result->failingScores);
        $this->assertSame(71, $result->overallAverage);
    }

    public function test_identify_weaknesses_lists_only_failing_scores(): void
    {
        $evaluator = new ContentQualityEvaluator;
        $scores = [
            'human_writing_index' => 80,
            'virality_score' => 50,
            'engagement_score' => 60,
            'roi_score' => 90,
            'trend_alignment' => 88,
        ];

        $weaknesses = $evaluator->identifyWeaknesses($scores, [
            'virality_score' => 'Weak hook',
            'engagement_score' => 'No clear CTA',
        ]);

        $this->assertCount(2, $weaknesses);
        $this->assertSame('virality_score', $weaknesses[0]['score']);
        $this->assertSame(20, $weaknesses[0]['gap']);
        $this->assertSame('Weak hook', $weaknesses[0]['explanation']);
        $this->assertSame('engagement_score', $weaknesses[1]['score']);
    }
}
