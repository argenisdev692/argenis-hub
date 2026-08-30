<?php

declare(strict_types=1);

namespace Modules\Post\Tests\Unit;

use Modules\Post\Domain\Services\PostContentQualityEvaluator;
use PHPUnit\Framework\TestCase;

final class PostContentQualityEvaluatorTest extends TestCase
{
    public function test_all_pass_when_every_threshold_is_met(): void
    {
        $result = (new PostContentQualityEvaluator)->evaluate([
            'human_writing_index' => 75,
            'eeat_score' => 70,
            'virality_score' => 70,
            'roi_score' => 70,
            'seo_score' => 70,
        ]);

        $this->assertTrue($result->allPass);
        $this->assertSame([], $result->failingScores);
        $this->assertSame(71, $result->overallAverage);
    }

    public function test_identify_weaknesses_lists_only_failing_scores(): void
    {
        $evaluator = new PostContentQualityEvaluator;
        $scores = [
            'human_writing_index' => 80,
            'eeat_score' => 60,
            'virality_score' => 50,
            'roi_score' => 90,
            'seo_score' => 88,
        ];

        $weaknesses = $evaluator->identifyWeaknesses($scores, [
            'eeat_score' => 'Missing citations',
            'virality_score' => 'Weak hook',
        ]);

        $this->assertCount(2, $weaknesses);
        $this->assertSame('eeat_score', $weaknesses[0]['score']);
        $this->assertSame(10, $weaknesses[0]['gap']);
        $this->assertSame('Missing citations', $weaknesses[0]['explanation']);
        $this->assertSame('virality_score', $weaknesses[1]['score']);
    }
}
