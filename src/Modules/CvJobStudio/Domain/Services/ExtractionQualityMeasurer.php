<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Requirement-extraction precision/recall harness (T-095, RK-3): scores a
 * labelled fixture (expected canonical names) against an extraction result.
 * The live measurement runs the fixture through the real extractor; the
 * maths here is provider-independent and unit-tested.
 */
final readonly class ExtractionQualityMeasurer
{
    /**
     * @param  list<string>  $expected  hand-labelled canonical names
     * @param  list<string>  $extracted  canonical names the extractor returned
     * @return array{precision: float, recall: float, f1: float, hallucinated: list<string>, missed: list<string>}
     */
    #[\NoDiscard]
    public function measure(array $expected, array $extracted): array
    {
        $expectedMap = array_fill_keys(array_map(SkillRelationSet::normalize(...), $expected), true);
        $extractedMap = array_fill_keys(array_map(SkillRelationSet::normalize(...), $extracted), true);

        $hits = count(array_intersect_key($extractedMap, $expectedMap));
        $precision = count($extractedMap) > 0 ? $hits / count($extractedMap) : 0.0;
        $recall = count($expectedMap) > 0 ? $hits / count($expectedMap) : 0.0;

        return [
            'precision' => $precision,
            'recall' => $recall,
            'f1' => $precision + $recall > 0.0 ? 2 * $precision * $recall / ($precision + $recall) : 0.0,
            'hallucinated' => array_keys(array_diff_key($extractedMap, $expectedMap)),
            'missed' => array_keys(array_diff_key($expectedMap, $extractedMap)),
        ];
    }
}
